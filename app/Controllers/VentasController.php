<?php
require_once BASE_PATH . '/app/Controllers/Controller.php';
require_once BASE_PATH . '/app/Models/VentaModel.php';
require_once BASE_PATH . '/core/AuditLog.php';
require_once BASE_PATH . '/core/PrecioHelper.php';

class VentasController extends Controller
{
    private VentaModel $model;

    public function __construct()
    {
        $this->model = new VentaModel();
    }

    /** Listado de ventas */
    public function index(): void
    {
        $this->requireAuth();

        // Mismo permiso que el stock teórico del inventario: sin él, el listado de ventas
        // queda fijo al día de hoy, para que no se pueda usar el histórico de ventas para
        // reconstruir "a mano" cuál debería ser el stock teórico antes de un conteo físico.
        $puedeVerHistorial = canView('stock_ver_teorico');
        $filtros = [
            'desde'  => $puedeVerHistorial ? ($this->param('desde') ?: date('Y-m-01')) : date('Y-m-d'),
            'hasta'  => $puedeVerHistorial ? ($this->param('hasta') ?: date('Y-m-d')) : date('Y-m-d'),
            'estado' => $this->param('estado'),
        ];
        // Sin 'ventas_ver_todas', cada usuario ve solo lo que él mismo facturó (nunca lo de
        // otro cajero/caja) — ver PermisosModel::MODULOS.
        if (!canView('ventas_ver_todas')) {
            $filtros['usuario_id'] = (int) Session::user()['id'];
        }

        $this->render('ventas.index', [
            'pageTitle'         => 'Ventas',
            'breadcrumb'        => ['Ventas' => null, 'Listado' => null],
            'ventas'            => $this->model->findAllConDetalle($filtros),
            'filtros'           => $filtros,
            'puedeVerHistorial' => $puedeVerHistorial,
        ]);
    }

    /** Comprobantes emitidos (o intentados) por AFIP */
    public function comprobantesAfip(): void
    {
        $this->requireAuth();

        $filtros = [
            'desde'     => $this->param('desde') ?: date('Y-m-01'),
            'hasta'     => $this->param('hasta') ?: date('Y-m-d'),
            'resultado' => $this->param('resultado', ''),
        ];

        $this->render('ventas.comprobantes-afip', [
            'pageTitle'  => 'Comprobantes AFIP',
            'breadcrumb' => ['Ventas' => 'ventas', 'Comprobantes AFIP' => null],
            'comprobantes' => $this->model->findComprobantesAfip($filtros),
            'filtros'    => $filtros,
        ]);
    }

    /** AJAX: consulta un comprobante directamente contra AFIP (WSFE), en tiempo real */
    public function verificarAfip(): void
    {
        $this->requireAuth();

        $id = (int)$this->param('id', 0);
        $venta = $this->model->findByIdConDetalle($id);
        if (!$venta || !$venta['afip_nro_cbte']) {
            $this->json(['ok' => false, 'error' => 'Esta venta no tiene un comprobante AFIP para verificar.'], 422);
        }

        require_once BASE_PATH . '/core/AfipWsfe.php';
        try {
            $res = AfipWsfe::consultarComprobante(
                (int)$venta['afip_pto_vta'],
                (int)$venta['afip_tipo_cbte'],
                (int)$venta['afip_nro_cbte']
            );
            $this->json($res);
        } catch (\Throwable $e) {
            logApp('Error verificando comprobante AFIP: ' . $e->getMessage(), 'ERROR');
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: trae en vivo desde AFIP los últimos N comprobantes de un Punto de Venta/Tipo dado.
     * Sirve para ver comprobantes emitidos por OTROS sistemas (ej. puntos de venta 1/2 del ERP
     * anterior) que nunca pasaron por la base de datos de Dewoc — WSFE no tiene un "listado
     * general", así que se arma consultando hacia atrás desde el último autorizado.
     */
    public function historicoAfip(): void
    {
        $this->requireAuth();

        $ptoVta   = (int)$this->param('pto_vta', 0);
        $tipoCbte = (int)$this->param('tipo_cbte', 11);
        $cantidad = min(50, max(1, (int)$this->param('cantidad', 20)));

        if (!$ptoVta) {
            $this->json(['ok' => false, 'error' => 'Indicá el punto de venta a consultar.'], 422);
        }

        require_once BASE_PATH . '/core/AfipWsfe.php';
        try {
            $lastNro = AfipWsfe::ultimoAutorizado($ptoVta, $tipoCbte);
            if ($lastNro <= 0) {
                $this->json(['ok' => true, 'comprobantes' => []]);
            }

            $desde = max(1, $lastNro - $cantidad + 1);
            $comprobantes = [];
            for ($nro = $lastNro; $nro >= $desde; $nro--) {
                $r = AfipWsfe::consultarComprobante($ptoVta, $tipoCbte, $nro);
                if ($r['encontrado'] ?? false) {
                    $r['nro_cbte']  = $nro;
                    $r['pto_vta']   = $ptoVta;
                    $r['tipo_cbte'] = $tipoCbte;
                    $comprobantes[] = $r;
                }
            }

            $this->json(['ok' => true, 'comprobantes' => $comprobantes]);
        } catch (\Throwable $e) {
            logApp('Error consultando histórico AFIP: ' . $e->getMessage(), 'ERROR');
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** POS (punto de venta) */
    public function pos(): void
    {
        $this->requireCanView('ventas_pos');

        $usuario = Session::user();
        $turno   = $this->model->getTurnoAbierto((int)$usuario['id']);

        $vista = $this->estadoAfipParaVista($this->calcularEstadoFacturacionAfip());

        $this->render('ventas.pos', [
            'pageTitle' => 'Punto de Venta',
            'breadcrumb'=> ['Ventas' => 'ventas', 'POS' => null],
            'turno'     => $turno,
            // Si el comprobante DISCRIMINA IVA (condición fiscal).
            'esResponsableInscripto' => PrecioHelper::discriminaIva(),
            // Si el precio CARGADO ya trae IVA (opción D9, configuración fiscal). Son dos
            // cosas distintas: ver core/PrecioHelper.php.
            'preciosIncluyenIva'     => PrecioHelper::preciosIncluyenIva(),
            'bloqueoFacturacionAfip' => $vista['bloqueoFacturacionAfip'],
            'catMono'             => $vista['catMono'],
            'limiteMonoMes'       => $vista['limiteMonoMes'],
            'facturadoAfipMes'    => $vista['facturadoAfipMes'],
            'pctMonoMes'          => $vista['pctMonoMes'],
            'prorateoVigente'     => $vista['prorateoVigente'],
            'disponibleHoy'       => $vista['disponibleHoy'],
            'pctUsadoHoy'         => $vista['pctUsadoHoy'],
            'cuotaHoyInicial'     => $vista['cuotaHoyInicial'],
            'facturadoHoy'        => $vista['facturadoHoy'],
            'prorateoHasta'       => $vista['prorateoHasta'],
            'limiteFacturacionAlcanzado' => $vista['limiteFacturacionAlcanzado'],
            'facturarAfipPorDefecto'     => $vista['facturarAfipPorDefecto'],
        ]);
    }

    /**
     * Deriva del estado crudo de calcularEstadoFacturacionAfip() los flags ya listos para
     * mostrar (límite alcanzado, checkbox tildado por defecto). Separado de ese método para
     * poder reusarlo tal cual desde guardar() — ahí se manda de vuelta en la respuesta JSON
     * (ver 'afipEstado' más abajo) para que el POS actualice el widget "Podés facturar hoy"
     * y el checkbox después de CADA venta, sin recargar la página. Antes esto solo se
     * calculaba una vez al cargar pos() y quedaba fijo en el navegador toda la sesión,
     * aunque el cálculo de atrás sí se recalculaba en cada venta (server-side).
     */
    private function estadoAfipParaVista(array $estado): array
    {
        // Solo el prorrateo diario tiene un tope diario real que bloquear (el acumulado
        // de 12 meses es informativo/no tiene un "día" al que atribuirle el corte).
        $limiteFacturacionAlcanzado = $estado['prorateoVigente'] && $estado['disponibleHoy'] !== null && $estado['disponibleHoy'] <= 0;

        // "Facturar por AFIP" arranca tildado salvo que ya esté bloqueado o que hoy ya
        // se haya llegado al 90% de la cuota diaria (ahí arranca destildado, pero no
        // deshabilitado — el cajero puede volver a tildarlo a criterio propio).
        $facturarAfipPorDefecto = !$estado['bloqueoFacturacionAfip']
            && !$limiteFacturacionAlcanzado
            && !($estado['pctUsadoHoy'] !== null && $estado['pctUsadoHoy'] >= 90);

        return $estado + [
            'limiteFacturacionAlcanzado' => $limiteFacturacionAlcanzado,
            'facturarAfipPorDefecto'     => $facturarAfipPorDefecto,
        ];
    }

    /**
     * Calcula el estado de facturación AFIP (bloqueo manual del admin + prorrateo diario o
     * acumulado de 12 meses según cuál esté vigente). Compartido entre pos() (para mostrarlo)
     * y guardar() (para revalidar server-side y detectar el cruce del 90% del tope diario).
     */
    private function calcularEstadoFacturacionAfip(): array
    {
        require_once BASE_PATH . '/app/Models/ConfiguracionFiscalModel.php';
        $fiscalModel     = new ConfiguracionFiscalModel();
        $configFiscal    = $fiscalModel->obtener();
        $catMono         = $configFiscal['categoria_monotributo'] ?? null;
        $categoriasMono  = ConfiguracionFiscalModel::categorias();
        $limiteMonoAnual = $catMono ? ($categoriasMono[$catMono] ?? null) : null;
        $limiteMonoManual   = $configFiscal['limite_facturacion_manual'] ?? null;
        $limiteMonoEfectivo = $limiteMonoManual !== null ? (float)$limiteMonoManual : $limiteMonoAnual;
        $facturadoAfip12Meses = $this->model->facturadoAfipUltimos12Meses();
        $pctMonoMes = $limiteMonoEfectivo ? min(100, ($facturadoAfip12Meses / $limiteMonoEfectivo) * 100) : null;

        // Prorrateo diario: si el admin configuró un saldo + período vigente (HOY entre
        // prorateo_desde y prorateo_hasta), reemplaza el indicador de 12 meses de arriba
        // por "cuánto se puede facturar hoy".
        $prorateoVigente = false;
        $disponibleHoy   = null;
        $pctUsadoHoy      = null;
        $cuotaHoyInicial  = null;
        $facturadoHoy     = null;
        $prorateoHasta   = $configFiscal['prorateo_hasta'] ?? null;

        $saldoProratear = $configFiscal['saldo_prorateo_diario'] ?? null;
        $prorateoDesde  = $configFiscal['prorateo_desde'] ?? null;
        $hoy            = date('Y-m-d');
        $ayer           = date('Y-m-d', strtotime('-1 day'));

        if ($saldoProratear !== null && $prorateoDesde && $prorateoHasta && $hoy >= $prorateoDesde && $hoy <= $prorateoHasta) {
            // Días que restan HASTA el vencimiento (no el total del período): el
            // disponible de hoy se recalcula cada día contra lo que falta, así que
            // si un día no se factura nada, el saldo se reparte entre menos días
            // restantes (sube la cuota diaria) en vez de diluirse en el promedio fijo.
            $diasRestantes = (strtotime($prorateoHasta) - strtotime($hoy)) / 86400 + 1;
            if ($diasRestantes > 0) {
                $facturadoAcumPeriodo = $this->model->facturadoAfipEnPeriodo($prorateoDesde, $hoy);
                $disponibleHoy        = ((float)$saldoProratear - $facturadoAcumPeriodo) / $diasRestantes;
                $prorateoVigente      = true;

                // % de la cuota de HOY ya consumido (para avisar al llegar al 90%). La cuota
                // de hoy es "disponibleHoy" tal como hubiera dado sin lo ya facturado hoy —
                // mismo cálculo, pero con el acumulado hasta ayer.
                $facturadoAntesDeHoy = $prorateoDesde <= $ayer
                    ? $this->model->facturadoAfipEnPeriodo($prorateoDesde, $ayer)
                    : 0.0;
                $cuotaHoyInicial = ((float)$saldoProratear - $facturadoAntesDeHoy) / $diasRestantes;
                $pctUsadoHoy     = $cuotaHoyInicial > 0
                    ? max(0.0, min(100.0, (1 - $disponibleHoy / $cuotaHoyInicial) * 100))
                    : 100.0;

                // Monto crudo facturado HOY (sin dividir por días restantes) — a diferencia
                // de "disponibleHoy" (que reparte lo que queda entre todos los días
                // restantes del período, así que una venta puntual apenas lo mueve cuando el
                // período es largo: ej. $50.000 facturados sobre 300 días restantes bajan
                // "disponibleHoy" apenas ~$166, parece que "no descontó"). Este número sí
                // refleja 1 a 1 lo que se facturó hoy, para que el cajero vea el efecto real
                // de cada venta sin tener que hacer la cuenta mentalmente.
                $facturadoHoy = $facturadoAcumPeriodo - $facturadoAntesDeHoy;
            }
        }

        return [
            'bloqueoFacturacionAfip' => !empty($configFiscal['bloquear_facturacion_afip']),
            'catMono'          => $catMono,
            'limiteMonoMes'    => $limiteMonoEfectivo,
            'facturadoAfipMes' => $facturadoAfip12Meses,
            'pctMonoMes'       => $pctMonoMes,
            'prorateoVigente'  => $prorateoVigente,
            'disponibleHoy'    => $disponibleHoy,
            'pctUsadoHoy'      => $pctUsadoHoy,
            'cuotaHoyInicial'  => $cuotaHoyInicial,
            'facturadoHoy'     => $facturadoHoy,
            'prorateoHasta'    => $prorateoHasta,
        ];
    }

    /** POST: guardar venta desde el POS */
    public function guardar(): void
    {
        $this->requireAuth();
        Csrf::check();

        $usuario   = Session::user();
        $turno     = $this->model->getTurnoAbierto((int)$usuario['id']);

        // Antes se podía vender sin turno abierto (quedaba turno_id=null y la venta no
        // aparecía en ningún cierre de caja) — ahora se exige turno abierto para cobrar. El
        // POS ya deshabilita "Cobrar" del lado del navegador (ver TURNO_ABIERTO en pos.php),
        // pero esto es lo que realmente lo impide: sin esto, reenviar el form a mano o
        // sincronizar una venta guardada offline seguiría registrando la venta igual.
        if (!$turno) {
            $this->json(['ok' => false, 'error' => 'No hay un turno de caja abierto. Abrí un turno antes de cobrar.'], 422);
        }

        $itemsJson       = $this->input('items_json', '[]');
        $items           = json_decode($itemsJson, true);
        $clienteId       = (int)$this->input('cliente_id') ?: null;
        // Si esta venta viene de aceptar un pedido del Portal de Clientes Mayoristas (ver
        // docs/portal-clientes-mayoristas-checklist.md), queda vinculada para marcarlo
        // "convertido" al confirmarse — ver más abajo, después de VentaModel::registrar().
        $pedidoClienteId = (int)$this->input('pedido_cliente_id') ?: null;
        $facturar   = (int)$this->input('facturar_afip', 0);
        $descGlobal = (float)$this->input('descuento_global', 0);
        $confirmarSinStock = (bool)(int)$this->input('confirmar_sin_stock', 0);

        // El admin puede bloquear "Facturar por AFIP" globalmente, y además se corta solo al
        // llegar al tope diario del prorrateo — se valida acá y no solo en el checkbox del
        // POS, para que no se pueda facturar igual reenviando el form a mano.
        $estadoAfipAntes = null;
        if ($facturar) {
            $estadoAfipAntes = $this->calcularEstadoFacturacionAfip();
            if ($estadoAfipAntes['bloqueoFacturacionAfip']) {
                $facturar = 0;
            } elseif ($estadoAfipAntes['prorateoVigente'] && $estadoAfipAntes['disponibleHoy'] !== null && $estadoAfipAntes['disponibleHoy'] <= 0) {
                $facturar = 0;
            }
        }
        $metodoPago = $this->input('metodo_pago', 'efectivo');
        $notasPos   = $this->input('notas', '') ?: null;
        $mesa       = trim($this->input('mesa', '')) ?: null;

        // Pago dividido entre varios medios (ej. parte efectivo, parte débito). Si no
        // vienen estos campos (flujo offline/legacy, que solo manda metodo_pago) se cae
        // al comportamiento de siempre: el monto completo va al medio único informado.
        $montosPorMedio = [
            'efectivo'      => (float)$this->input('monto_efectivo', 0),
            'debito'        => (float)$this->input('monto_debito', 0),
            'credito'       => (float)$this->input('monto_credito', 0),
            'transferencia' => (float)$this->input('monto_transferencia', 0),
            'mercadopago'   => (float)$this->input('monto_mercadopago', 0),
            'cuenta_corriente' => (float)$this->input('monto_cuenta_corriente', 0),
        ];
        $sumaMontos = round(array_sum($montosPorMedio), 2);

        if (!is_array($items) || !count($items)) {
            $this->json(['ok' => false, 'error' => 'No hay productos en la venta.'], 422);
        }

        $subtotal = 0.0;
        $totalIva = 0.0;
        $detalle  = [];

        foreach ($items as $it) {
            $pid    = (int)($it['producto_id'] ?? 0);
            $cant   = (float)($it['cantidad'] ?? 0);
            $precio = (float)($it['precio_unitario'] ?? 0);
            $ivaProd= (float)($it['iva_porcentaje'] ?? 21);
            $desc   = (float)($it['descuento_pct'] ?? 0);

            if (!$pid || $cant <= 0) continue;

            // Un solo lugar decide cómo se interpreta el precio cargado (opción D9) y si el
            // comprobante discrimina IVA (condición fiscal): core/PrecioHelper.php. Estos
            // montos son los que se le muestran al cajero en la respuesta y los que validan
            // el desglose por medio de pago; VentaModel::registrar() los vuelve a calcular
            // con el mismo helper antes de guardar, así el total nunca puede divergir.
            $iva    = PrecioHelper::ivaEfectivo($ivaProd);
            $final  = PrecioHelper::precioFinal($precio, $ivaProd);
            $subIva = round($final * $cant * (1 - $desc / 100), 4);
            $sub    = PrecioHelper::desglosar($subIva, $iva, 4)['neto'];

            $subtotal += $sub;
            $totalIva += $subIva - $sub;

            // Opciones/adicionales elegidas para esta línea (ver docs/tareas-pendientes.md
            // #1) — acá solo se sanean los dos campos que importan (id + cuántas veces);
            // VentaModel::registrar() vuelve a buscar cada opción por su cuenta en la base
            // (nombre, a qué descuenta stock, cantidad_consumida), no confía en nada más
            // que el cliente mande sobre ella.
            $opciones = [];
            foreach (($it['opciones'] ?? []) as $op) {
                $opcionId = (int)($op['opcion_id'] ?? 0);
                if (!$opcionId) continue;
                $opciones[] = ['opcion_id' => $opcionId, 'cantidad_elegida' => max(1, (int)($op['cantidad_elegida'] ?? 1))];
            }

            // Sabor elegido POR UNIDAD para cada componente de una promo (ver
            // VentaModel::explotarPromos()) — mismo saneo que $opciones arriba, pero
            // agrupado por componente_producto_id y con un array por unidad.
            $opcionesPorComponente = [];
            foreach (($it['opciones_por_componente'] ?? []) as $componenteId => $unidades) {
                $componenteId = (int)$componenteId;
                if (!$componenteId || !is_array($unidades)) continue;
                $unidadesSaneadas = [];
                foreach ($unidades as $seleccionUnidad) {
                    $unidad = [];
                    foreach ((is_array($seleccionUnidad) ? $seleccionUnidad : []) as $op) {
                        $opcionId = (int)($op['opcion_id'] ?? 0);
                        if (!$opcionId) continue;
                        $unidad[] = ['opcion_id' => $opcionId, 'cantidad_elegida' => max(1, (int)($op['cantidad_elegida'] ?? 1))];
                    }
                    $unidadesSaneadas[] = $unidad;
                }
                $opcionesPorComponente[$componenteId] = $unidadesSaneadas;
            }

            $detalle[] = [
                'producto_id'    => $pid,
                'cantidad'       => $cant,
                'precio_unitario'=> $precio,
                'descuento_pct'  => $desc,
                'subtotal'       => $sub,
                'iva_porcentaje' => $iva,
                'subtotal_con_iva'=> $subIva,
                'opciones'       => $opciones,
                'opciones_por_componente' => $opcionesPorComponente,
            ];
        }

        if (!count($detalle)) {
            $this->json(['ok' => false, 'error' => 'Items inválidos.'], 422);
        }

        // Aplicar descuento global
        if ($descGlobal > 0 && $descGlobal <= 100) {
            $factor    = 1 - $descGlobal / 100;
            $subtotal  = round($subtotal * $factor, 2);
            $totalIva  = round($totalIva * $factor, 2);
        }

        $totalConIva = round($subtotal + $totalIva, 2);

        if ($sumaMontos > 0) {
            // Vino desglose por medio: tiene que cerrar exacto contra el total (tolerancia
            // de un centavo por redondeos de punto flotante).
            if (abs($sumaMontos - $totalConIva) > 0.01) {
                $this->json(['ok' => false, 'error' => 'La suma de los medios de pago ($' . number_format($sumaMontos, 2, ',', '.') . ') no coincide con el total de la venta ($' . number_format($totalConIva, 2, ',', '.') . ').'], 422);
            }
            $mediosUsados = array_keys(array_filter($montosPorMedio, fn($m) => $m > 0));
            $metodoPago   = count($mediosUsados) === 1 ? $mediosUsados[0] : 'combinado';
        } else {
            // Legacy/offline: todo el total va al medio único informado.
            if (!array_key_exists($metodoPago, $montosPorMedio)) $metodoPago = 'efectivo';
            $montosPorMedio[$metodoPago] = $totalConIva;
        }

        if ($montosPorMedio['cuenta_corriente'] > 0) {
            if (!$clienteId) {
                $this->json(['ok' => false, 'error' => 'No se puede vender a cuenta corriente sin seleccionar un cliente.'], 422);
            }
            require_once BASE_PATH . '/app/Models/CobroClienteModel.php';
            require_once BASE_PATH . '/app/Models/ClienteModel.php';
            $saldoActual = (new CobroClienteModel())->getSaldoPendienteCliente($clienteId);
            $cliente     = (new ClienteModel())->findById($clienteId);
            $limite      = ($cliente && $cliente['limite_cuenta_corriente'] !== null) ? (float)$cliente['limite_cuenta_corriente'] : null;
            $tope        = $limite ?? CobroClienteModel::TOPE_DEFAULT;
            if ($saldoActual + $montosPorMedio['cuenta_corriente'] > $tope + 0.01) {
                $this->json(['ok' => false, 'error' => 'El cliente superaría su tope de cuenta corriente (saldo actual $'
                    . number_format($saldoActual, 2, ',', '.') . ' + esta venta > tope $' . number_format($tope, 2, ',', '.') . ').'], 422);
            }
        }

        $cabecera = [
            'usuario_id'     => (int)$usuario['id'],
            'turno_id'       => $turno ? (int)$turno['id'] : null,
            'cliente_id'     => $clienteId,
            'subtotal'       => round($subtotal, 2),
            'total_iva'      => round($totalIva, 2),
            'total_con_iva'  => $totalConIva,
            'descuento_global'=> $descGlobal,
            'metodo_pago'    => $metodoPago,
            'monto_efectivo'      => $montosPorMedio['efectivo'],
            'monto_debito'        => $montosPorMedio['debito'],
            'monto_credito'       => $montosPorMedio['credito'],
            'monto_transferencia' => $montosPorMedio['transferencia'],
            'monto_mercadopago'   => $montosPorMedio['mercadopago'],
            'monto_cuenta_corriente' => $montosPorMedio['cuenta_corriente'],
            'pedido_cliente_id' => $pedidoClienteId,
            'facturar_afip'  => $facturar,
            'estado'         => 'completada',
            'mesa'           => $mesa,
            'notas'          => $notasPos,
            'eliminado'      => 0,
            // Se fija explícito en vez de confiar en el DEFAULT CURRENT_TIMESTAMP de
            // MySQL: el servidor de base de datos puede estar en otra zona horaria
            // (ej. UTC) mientras que PHP ya está configurado en TIMEZONE (config.php).
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        try {
            $resultado = $this->model->registrar($cabecera, $detalle, $confirmarSinStock);
            if (!$resultado['ok']) {
                // requiereConfirmacion=true: no es un error duro, es un aviso — el POS le
                // muestra al usuario qué falta y, si confirma, reenvía con confirmar_sin_stock=1.
                $this->json([
                    'ok'                  => false,
                    'requiereConfirmacion'=> !empty($resultado['requiereConfirmacion']),
                    'error'               => 'No hay suficiente materia prima/insumos para completar la venta.',
                    'faltantes'           => $resultado['faltantes'],
                ], 409);
            }
            $id    = $resultado['id'];
            $venta = $this->model->findByIdConDetalle($id);

            if ($pedidoClienteId) {
                require_once BASE_PATH . '/app/Models/PedidoClienteModel.php';
                (new PedidoClienteModel())->marcarConvertido($pedidoClienteId, $id);
            }

            // AFIP: solicitar CAE si está configurado y se pidió facturar
            $afipData         = null;
            $estadoAfipDespues = null;
            if ($facturar && defined('AFIP_CUIT') && AFIP_CUIT) {
                try {
                    require_once BASE_PATH . '/core/AfipWsaa.php';
                    require_once BASE_PATH . '/core/AfipWsfe.php';
                    $afipData = AfipWsfe::solicitarCae($venta, $detalle);
                    if ($afipData['ok']) {
                        $this->model->updateAfip($id, $afipData);

                        // Si esta venta hizo cruzar el 90% de la cuota diaria (y no lo estaba
                        // ya antes de facturarla, para no repetir el aviso en cada venta
                        // siguiente del mismo día), se avisa por push.
                        $estadoAfipDespues = $this->calcularEstadoFacturacionAfip();
                        $yaEstabaAlNoventa = $estadoAfipAntes && $estadoAfipAntes['pctUsadoHoy'] !== null && $estadoAfipAntes['pctUsadoHoy'] >= 90;
                        if (!$yaEstabaAlNoventa && $estadoAfipDespues['prorateoVigente'] && $estadoAfipDespues['pctUsadoHoy'] !== null && $estadoAfipDespues['pctUsadoHoy'] >= 90) {
                            $this->notificarLimiteFacturacionAfip(
                                $estadoAfipDespues['pctUsadoHoy'],
                                (float)$estadoAfipDespues['disponibleHoy'],
                                $estadoAfipDespues['prorateoHasta']
                            );
                        }
                    }
                } catch (\Throwable $afipEx) {
                    $afipData = ['ok' => false, 'error' => $afipEx->getMessage()];
                    logApp('AFIP error en venta #' . $id . ': ' . $afipEx->getMessage(), 'ERROR');
                }
            }

            // Estado AFIP posterior a esta venta, para que el POS actualice el widget
            // "Podés facturar hoy" y el checkbox sin recargar la página (ver
            // estadoAfipParaVista()). Reusa el ya calculado arriba si esta venta facturó y
            // cruzó el 90% (evita una tercera consulta); si no, lo calcula fresco acá —
            // aunque esta venta no haya facturado por AFIP, igual puede haber cambiado el
            // "hoy" en curso respecto de cuando cargó la página (turno largo, otra caja
            // facturando en paralelo, etc.).
            $vistaAfipFinal = $this->estadoAfipParaVista($estadoAfipDespues ?? $this->calcularEstadoFacturacionAfip());

            $this->json([
                'ok'      => true,
                'id'      => $id,
                'mensaje' => 'Venta registrada correctamente.',
                'ticket_url'       => url("ventas/ticket?id=$id&token=" . ticketToken($id)),
                'comanda_url'      => url("ventas/comanda?id=$id"),
                'total'            => (float)($venta['total_con_iva'] ?? 0),
                'cliente_telefono' => $venta['cliente_telefono'] ?? '',
                'afip'             => $afipData,
                'afipEstado'       => $vistaAfipFinal,
            ]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Ver detalle de una venta */
    public function ver(): void
    {
        $this->requireAuth();
        $id = (int)$this->param('id');
        // Mismo filtro que index(): sin 'ventas_ver_todas', ni siquiera escribiendo el id de
        // una venta ajena directo en la URL se puede ver — devuelve 404, igual que si no
        // existiera, para no revelar si el id pertenece a otro usuario.
        $usuarioFiltro = canView('ventas_ver_todas') ? null : (int) Session::user()['id'];
        $venta = $this->model->findByIdConDetalle($id, $usuarioFiltro);
        if (!$venta) { $this->render('errors.404', ['pageTitle' => 'No encontrado', 'breadcrumb' => []]); return; }

        require_once BASE_PATH . '/app/Models/SolicitudAnulacionVentaModel.php';
        $solicitudAnulacion = (new SolicitudAnulacionVentaModel())->findUltimaPorVenta($id);

        $this->render('ventas.ver', [
            'pageTitle'           => 'Venta #' . $id,
            'breadcrumb'          => ['Ventas' => 'ventas', 'Ver' => null],
            'venta'               => $venta,
            'solicitudAnulacion'  => $solicitudAnulacion,
        ]);
    }

    /**
     * Anulación directa — solo admin. Un cajero que no sea admin ya no puede anular ventas
     * por acá: tiene que pasar por solicitarAnulacion() y que un admin la apruebe (mismo
     * motivo por el que existe el "cierre ciego" de caja — si el mismo cajero que declara
     * un faltante pudiera anular ventas libremente, ese control no controlaría nada).
     */
    public function anular(): void
    {
        $this->requireRole('admin');
        Csrf::check();

        $id = (int)$this->input('id');
        try {
            $ok = $this->model->anular($id);
            if ($ok) {
                AuditLog::log('anular', 'ventas', "Venta #{$id} anulada");
                $this->json(['ok' => true, 'mensaje' => 'Venta anulada.']);
            } else {
                $this->json(['ok' => false, 'error' => 'No se pudo anular.'], 409);
            }
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Corrige el medio de pago de una venta ya cobrada (ej. se facturó con Mercado Pago
     * por error y en realidad se cobró en efectivo) — sin pasar por anular+recargar. Ver
     * VentaModel::cambiarMedioPago() para el detalle de qué mueve.
     */
    public function cambiarMedioPago(): void
    {
        $this->requireCanEdit('ventas');
        Csrf::check();

        $id     = (int)$this->input('id');
        $nuevo  = (string)$this->input('metodo_pago', '');

        try {
            $ok = $this->model->cambiarMedioPago($id, $nuevo);
            if ($ok) {
                AuditLog::log('cambiar_medio_pago', 'ventas', "Venta #{$id} — medio de pago cambiado a {$nuevo}");
                $this->json(['ok' => true, 'mensaje' => 'Medio de pago actualizado.']);
            } else {
                $this->json(['ok' => false, 'error' => 'No se pudo actualizar (¿venta inexistente o anulada?).'], 409);
            }
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Un usuario sin permiso para anular directamente pide la anulación, con motivo obligatorio. */
    public function solicitarAnulacion(): void
    {
        $this->requireAuth();
        Csrf::check();

        require_once BASE_PATH . '/app/Models/SolicitudAnulacionVentaModel.php';

        $ventaId       = (int) $this->input('venta_id');
        $motivo        = trim($this->input('motivo', ''));
        $motivoDetalle = trim($this->input('motivo_detalle', ''));

        $venta = $this->model->findByIdConDetalle($ventaId);
        if (!$venta) {
            $this->json(['ok' => false, 'error' => 'Venta no encontrada.'], 404);
        }
        if ($venta['estado'] === 'anulada') {
            $this->json(['ok' => false, 'error' => 'Esta venta ya está anulada.'], 409);
        }
        if ($motivo === '') {
            $this->json(['ok' => false, 'error' => 'Indicá el motivo de la anulación.'], 422);
        }
        if ($motivo === 'Otro' && $motivoDetalle === '') {
            $this->json(['ok' => false, 'error' => 'Contá brevemente el motivo.'], 422);
        }

        $solicitudModel = new SolicitudAnulacionVentaModel();
        if ($solicitudModel->findPendientePorVenta($ventaId)) {
            $this->json(['ok' => false, 'error' => 'Ya hay una solicitud de anulación pendiente para esta venta.'], 409);
        }

        $usuario = Session::user();
        $solicitudModel->crear($ventaId, (int) $usuario['id'], $motivo, $motivoDetalle ?: null);

        AuditLog::log('solicitar_anulacion', 'ventas', "Venta #{$ventaId} — solicitó anulación: {$motivo}");

        $this->notificarAdminsAnulacion($ventaId, $motivo, (string) ($usuario['nombre'] ?? 'Un usuario'));

        $this->json(['ok' => true, 'mensaje' => 'Solicitud de anulación enviada. Un admin la va a revisar.']);
    }

    /** Aprueba (ejecuta la anulación real) o rechaza una solicitud pendiente — requiere permiso de edición sobre el módulo. */
    public function resolverAnulacion(): void
    {
        $this->requireCanEdit('ventas_anulaciones');
        Csrf::check();

        require_once BASE_PATH . '/app/Models/SolicitudAnulacionVentaModel.php';

        $solicitudId   = (int) $this->input('solicitud_id');
        $accion        = $this->input('accion', '');
        $motivoRechazo = trim($this->input('motivo_rechazo', ''));

        if (!in_array($accion, ['aprobar', 'rechazar'], true)) {
            $this->json(['ok' => false, 'error' => 'Acción inválida.'], 422);
        }

        $solicitudModel = new SolicitudAnulacionVentaModel();
        $solicitud      = $solicitudModel->findById($solicitudId);
        if (!$solicitud || $solicitud['estado'] !== 'pendiente') {
            $this->json(['ok' => false, 'error' => 'Esta solicitud ya fue resuelta.'], 409);
        }

        $usuario   = Session::user();
        $ventaId   = (int) $solicitud['venta_id'];

        if ($accion === 'aprobar') {
            try {
                $ok = $this->model->anular($ventaId);
            } catch (\Throwable $e) {
                $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }
            if (!$ok) {
                $this->json(['ok' => false, 'error' => 'No se pudo anular la venta (¿ya estaba anulada?).'], 409);
            }
            $solicitudModel->resolver($solicitudId, 'aprobada', (int) $usuario['id']);
            AuditLog::log('aprobar_anulacion', 'ventas', "Venta #{$ventaId} — anulación aprobada (solicitud #{$solicitudId})");
            $this->json(['ok' => true, 'mensaje' => 'Anulación aprobada. La venta quedó anulada.']);
        }

        $solicitudModel->resolver($solicitudId, 'rechazada', (int) $usuario['id'], $motivoRechazo ?: null);
        AuditLog::log('rechazar_anulacion', 'ventas', "Venta #{$ventaId} — anulación rechazada (solicitud #{$solicitudId})");
        $this->json(['ok' => true, 'mensaje' => 'Solicitud rechazada.']);
    }

    /** Listado de solicitudes de anulación, pensado para revisar desde el celular. */
    public function solicitudesAnulacion(): void
    {
        $this->requireCanView('ventas_anulaciones');
        require_once BASE_PATH . '/app/Models/SolicitudAnulacionVentaModel.php';

        $estado = $this->param('estado', '');

        // Quien puede resolver (Editar) ve todas, para poder actuar sobre cualquiera; el
        // resto solo ve las que ÉL MISMO pidió — no hay "asignación" acá, así que no se
        // comparte con nadie más (a diferencia de Solicitudes, que sí se comparte por caja
        // abierta).
        $usuario   = Session::user();
        $usuarioId = canEdit('ventas_anulaciones') ? null : (int)$usuario['id'];

        $this->render('ventas.solicitudes_anulacion', [
            'pageTitle'   => 'Solicitudes de anulación',
            'breadcrumb'  => ['Ventas' => 'ventas', 'Solicitudes de anulación' => null],
            'solicitudes' => (new SolicitudAnulacionVentaModel())->findAllConDetalle($estado, $usuarioId),
            'filtroEstado'=> $estado,
        ]);
    }

    /**
     * Push a todos los admins avisando que hay una anulación de venta pendiente de revisar.
     * Mismo patrón que SolicitudesController::notificarAdmins() (no se toca esa función para
     * no arriesgar el flujo de solicitudes de compra, que ya funciona).
     */
    private function notificarAdminsAnulacion(int $ventaId, string $motivo, string $solicitanteNombre): void
    {
        if (!VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) return;

        require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
        require_once BASE_PATH . '/app/Models/NotificacionModel.php';
        require_once BASE_PATH . '/core/WebPush.php';

        $titulo = "Solicitud de anulación — Venta #{$ventaId}";
        $cuerpo = "{$solicitanteNombre} pidió anularla: {$motivo}";
        $url    = rtrim(BASE_URL, '/') . '/ventas/ver?id=' . $ventaId;
        $payload = json_encode([
            'title' => $titulo,
            'body'  => $cuerpo,
            'url'   => $url,
            'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
        ]);

        // No se limita al rol admin: cualquier usuario con "Solicitudes de Anulación"
        // habilitado en sus permisos personalizados (ej. un encargado) también recibe el
        // aviso, ya que es quien puede aprobar/rechazar (requireCanEdit en resolverAnulacion()).
        $pushModel = new PushSubscriptionModel();
        $suscripciones = $pushModel->findSuscripcionesConPermiso('ventas_anulaciones', 'editar');
        (new NotificacionModel())->crearParaSuscripciones($suscripciones, $titulo, $cuerpo, $url, 'venta_anulacion');
        foreach ($suscripciones as $sub) {
            try {
                $res = WebPush::enviar(
                    ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                    $payload,
                    VAPID_PUBLIC_KEY,
                    VAPID_PRIVATE_KEY,
                    VAPID_SUBJECT
                );
                if (in_array($res['status'], [404, 410], true)) {
                    $pushModel->eliminarPorId((int) $sub['id']);
                }
            } catch (\Throwable $e) {
                logApp('Error enviando push de anulación a suscripción #' . $sub['id'] . ': ' . $e->getMessage(), 'ERROR');
            }
        }
    }

    /**
     * Push avisando que se llegó al 90% de la cuota diaria de facturación AFIP. Va al
     * cajero que hizo la venta (siempre) más a quien el admin haya habilitado el módulo
     * "Alertas: Límite de facturación AFIP" en Permisos/permisos personalizados — mismo
     * patrón que notificarAdminsAnulacion().
     */
    private function notificarLimiteFacturacionAfip(float $pctUsadoHoy, float $disponibleHoy, ?string $prorateoHasta): void
    {
        if (!VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) return;

        require_once BASE_PATH . '/app/Models/PushSubscriptionModel.php';
        require_once BASE_PATH . '/app/Models/NotificacionModel.php';
        require_once BASE_PATH . '/core/WebPush.php';

        $usuario   = Session::user();
        $pushModel = new PushSubscriptionModel();

        $subs = $pushModel->findSuscripcionesConPermiso('alertas_facturacion_afip', 'ver');
        $endpointsYaIncluidos = array_column($subs, 'endpoint');
        foreach ($pushModel->findWhere(['usuario_id' => (int)$usuario['id']]) as $subPropia) {
            if (!in_array($subPropia['endpoint'], $endpointsYaIncluidos, true)) {
                $subs[] = $subPropia;
            }
        }
        if (!$subs) return;

        $titulo = '⚠️ Facturación AFIP al ' . round($pctUsadoHoy) . '% del límite diario';
        $cuerpo = 'Disponible hoy: $' . number_format($disponibleHoy, 2, ',', '.')
                . ($prorateoHasta ? '. Prorrateo vence el ' . date('d/m/Y', strtotime($prorateoHasta)) . '.' : '.');
        $url    = rtrim(BASE_URL, '/') . '/ventas/pos';
        $payload = json_encode([
            'title' => $titulo,
            'body'  => $cuerpo,
            'url'   => $url,
            'icon'  => rtrim(BASE_URL, '/') . '/assets/favicon/favicon.png',
        ]);

        (new NotificacionModel())->crearParaSuscripciones($subs, $titulo, $cuerpo, $url, 'afip_limite');
        foreach ($subs as $sub) {
            try {
                $res = WebPush::enviar(
                    ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
                    $payload,
                    VAPID_PUBLIC_KEY,
                    VAPID_PRIVATE_KEY,
                    VAPID_SUBJECT
                );
                if (in_array($res['status'], [404, 410], true)) {
                    $pushModel->eliminarPorId((int) $sub['id']);
                }
            } catch (\Throwable $e) {
                logApp('Error enviando push de límite de facturación AFIP a suscripción #' . $sub['id'] . ': ' . $e->getMessage(), 'ERROR');
            }
        }
    }

    /** Ticket para imprimir (80mm). Se puede ver sin login con un ?token= válido
     *  (link compartido por WhatsApp/email a un cliente que no tiene sesión). */
    public function ticket(): void
    {
        $id       = (int)$this->param('id');
        $token    = (string)$this->param('token', '');
        $esperado = ticketToken($id);
        // Las dos comprobaciones de "no vacío" son necesarias, no redundantes: sin ellas,
        // una visita sin ?token= contra una instalación sin TICKET_SHARE_SECRET cargado
        // compararía '' contra '', hash_equals() daría true y el ticket quedaría visible
        // sin sesión. Con esto, el acceso sin login exige un token real y un secreto real.
        if (!$id || $token === '' || $esperado === '' || !hash_equals($esperado, $token)) {
            $this->requireAuth();
        }
        require_once BASE_PATH . '/core/AfipWsfe.php';
        $venta = $this->model->findByIdConDetalle($id);
        if (!$venta) { http_response_code(404); echo '404 Not Found'; return; }

        // Vista minimal sin layout para imprimir
        $this->view('ventas.ticket', ['venta' => $venta]);
    }

    /** Comanda de cocina */
    public function comanda(): void
    {
        $this->requireAuth();
        require_once BASE_PATH . '/core/AfipWsfe.php';
        $id    = (int)$this->param('id');
        $venta = $this->model->findByIdConDetalle($id);
        if (!$venta) { http_response_code(404); echo '404 Not Found'; return; }

        $this->view('ventas.comanda', ['venta' => $venta]);
    }

    /** AJAX: búsqueda de productos para el POS */
    public function buscarProductos(): void
    {
        $this->requireAuth();
        $q = $this->param('q', '');
        if (strlen($q) < 1) { $this->json([]); }
        $this->json($this->model->buscarProductos($q));
    }

    /** AJAX: categorías con productos disponibles, para el selector visual del POS */
    public function categoriasPos(): void
    {
        $this->requireAuth();
        $this->json($this->model->categoriasPos());
    }

    /** AJAX: productos de una categoría, para el selector visual del POS */
    public function productosPorCategoria(): void
    {
        $this->requireAuth();
        $categoriaId = (int)$this->param('categoria_id', 0);
        if (!$categoriaId) { $this->json([]); }
        $this->json($this->model->productosPorCategoria($categoriaId));
    }

    /** AJAX: catálogo completo (productos + categorías) para que el POS lo cachee offline */
    public function catalogoOffline(): void
    {
        $this->requireAuth();
        $this->json($this->model->catalogoOffline());
    }

    /**
     * AJAX: grupos de opciones/adicionales configurados para un producto, con sus
     * opciones — el POS lo pide al clickear un producto con tiene_opciones=1, para armar
     * el modal de selección (ver docs/tareas-pendientes.md #1).
     */
    public function opcionesProducto(): void
    {
        $this->requireAuth();
        $productoId = (int)$this->param('producto_id', 0);
        if (!$productoId) { $this->json(['ok' => false, 'error' => 'ID inválido.'], 422); }

        require_once BASE_PATH . '/app/Models/ProductoModel.php';
        require_once BASE_PATH . '/app/Models/GrupoOpcionModel.php';
        $prodModel  = new ProductoModel();
        $grupoModel = new GrupoOpcionModel();

        $resolverGrupos = function (int $pid) use ($prodModel, $grupoModel): array {
            $grupos = $prodModel->getGruposOpciones($pid);
            foreach ($grupos as &$g) {
                $conOpciones = $grupoModel->findByIdConOpciones((int)$g['id']);
                // stock_actual: el de la materia prima/insumo/producto vinculado (null si la
                // opción es puramente informativa, ej. "sin azúcar", sin nada que descontar, o
                // si es un producto que no controla stock) — el front la usa para pintar en gris
                // las opciones sin stock y pedir confirmación antes de elegirlas, mismo criterio
                // que un producto agotado del catálogo.
                $g['opciones'] = array_map(fn($o) => [
                    'id'           => (int)$o['id'],
                    'nombre'       => $o['nombre'],
                    'stock_actual' => $o['materia_prima_id'] !== null
                        ? (float)$o['mp_stock_actual']
                        : ($o['insumo_id'] !== null
                            ? (float)$o['ins_stock_actual']
                            : ($o['producto_id'] !== null && !empty($o['prod_controla_stock']) ? (float)$o['prod_stock_actual'] : null)),
                    'unidad'       => $o['unidad_abrev'] ?? '',
                ], $conOpciones['opciones'] ?? []);
            }
            unset($g);
            return $grupos;
        };

        // Promo (ver producto_promo_detalle / VentaModel::explotarPromos()): además de sus
        // componentes fijos, una promo puede tener DOS fuentes de sabor/elección distintas:
        //  - "grupos_promo": opciones asignadas DIRECTO a la promo (ej. "elegí 1 cookie" en
        //    "PROMO: Café + cookie") — es una elección aparte del todo, no por unidad de
        //    ningún componente; el propio armado del producto ya lo aclara ("Opciones/
        //    adicionales" arriba de "Componentes de la promo"). Se resuelve y se descuenta
        //    igual que en un producto simple (vía _ajustarStockOpcion sobre la línea de la
        //    promo) — NO pasa por explotarPromos().
        //  - "componentes": cada componente fijo (ej. "1/4kg" x2) trae SU PROPIO selector de
        //    sabores, repetido una vez por unidad, para que cada unidad se elija por separado
        //    (no un pool combinado a repartir entre las dos) — ver corrección de "doble
        //    descuento" en VentaModel::registrar(), el consumo real sale de la receta de
        //    cada componente.
        $componentesPromo = $this->model->componentesPromo($productoId);

        if ($componentesPromo) {
            $gruposPromo = $resolverGrupos($productoId);

            $componentes = [];
            foreach ($componentesPromo as $c) {
                $grupos = $resolverGrupos((int)$c['componente_producto_id']);
                if (!$grupos) continue; // este componente no tiene sabor/opciones para elegir
                $componentes[] = [
                    'producto_id' => (int)$c['componente_producto_id'],
                    'nombre'      => $c['nombre'],
                    'imagen'      => ($c['imagen'] && file_exists(BASE_PATH . '/uploads/' . $c['imagen'])) ? (ASSETS_URL . '/../uploads/' . $c['imagen']) : null,
                    'unidades'    => (int)round((float)$c['cantidad']),
                    'grupos'      => $grupos,
                ];
            }
            $this->json(['ok' => true, 'es_promo' => true, 'grupos_promo' => $gruposPromo, 'componentes' => $componentes]);
        }

        $this->json(['ok' => true, 'es_promo' => false, 'grupos' => $resolverGrupos($productoId)]);
    }

    /**
     * AJAX: previsualiza el archivo de "Detalle de productos vendidos por pedido"
     * (exportado desde el ERP anterior) sin insertar nada — devuelve {ok, filas, resumen}.
     */
    public function previsualizar(): void
    {
        $this->requireAuth();
        Csrf::check();
        require_once BASE_PATH . '/core/ImportHelper.php';

        [$rows, $error] = $this->_leerArchivoImport();
        if ($error) $this->json(['ok' => false, 'error' => $error], 422);

        $db = Database::getInstance();
        $prodRows = $db->query("SELECT id, nombre FROM productos WHERE eliminado=0")->fetchAll();
        $prodIdx  = [];
        foreach ($prodRows as $p) { $prodIdx[self::_norm($p['nombre'])] = $p; }

        $pedidos = [];  // numero => [key => true]
        $noMatch = [];  // key => ['nombre'=>, 'pedidos'=>[], 'lineas'=>0]

        foreach ($rows as $row) {
            $numero = trim($row['Numero']   ?? '');
            $nombre = trim($row['Producto'] ?? '');
            if ($numero === '' || $nombre === '') continue;

            $key = self::_norm($nombre);
            $pedidos[$numero][$key] = true;

            if (!isset($prodIdx[$key])) {
                $noMatch[$key] ??= ['nombre' => $nombre, 'pedidos' => [], 'lineas' => 0];
                $noMatch[$key]['pedidos'][$numero] = true;
                $noMatch[$key]['lineas']++;
            }
        }

        $totalPedidos = count($pedidos);
        $pedidosConError = [];
        foreach ($pedidos as $numero => $keys) {
            foreach (array_keys($keys) as $key) {
                if (isset($noMatch[$key])) { $pedidosConError[$numero] = true; break; }
            }
        }

        $resumen = [
            'ok'      => $totalPedidos - count($pedidosConError),
            'warning' => 0,
            'error'   => count($pedidosConError),
        ];

        $filas = [];
        $fila  = 1;
        foreach ($noMatch as $info) {
            $fila++;
            $filas[] = [
                'fila'    => $fila,
                'titulo'  => $info['nombre'],
                'estado'  => 'error',
                'mensaje' => count($info['pedidos']) . ' pedido(s) afectado(s) · ' . $info['lineas'] . ' línea(s) sin coincidencia en Productos.',
            ];
        }
        if (empty($filas)) {
            $filas[] = [
                'fila' => 1, 'titulo' => 'Todos los productos coinciden', 'estado' => 'ok',
                'mensaje' => "Los {$totalPedidos} pedido(s) del archivo están listos para importar.",
            ];
        }

        $this->json(['ok' => true, 'filas' => $filas, 'resumen' => $resumen]);
    }

    /**
     * POST: importa el archivo de "Detalle de productos vendidos por pedido" del ERP anterior.
     * Cada "Numero" de pedido se agrupa en una venta histórica (sin descontar stock ni
     * generar movimiento de caja). Es seguro reintentar: los pedidos ya importados se omiten.
     */
    public function importar(): void
    {
        $this->requireAuth();
        Csrf::check();
        require_once BASE_PATH . '/core/ImportHelper.php';
        set_time_limit(0);

        [$rows, $error] = $this->_leerArchivoImport();
        if ($error) $this->json(['ok' => false, 'error' => $error], 422);

        $db = Database::getInstance();
        $prodRows = $db->query("SELECT id, nombre, iva FROM productos WHERE eliminado=0")->fetchAll();
        $prodIdx  = [];
        foreach ($prodRows as $p) { $prodIdx[self::_norm($p['nombre'])] = $p; }

        $yaImportados = array_flip($this->model->getNotasImportadasExistentes());

        // Agrupar filas por Numero de pedido
        $pedidos = [];
        foreach ($rows as $row) {
            $numero = trim($row['Numero'] ?? '');
            if ($numero === '') continue;
            $pedidos[$numero][] = $row;
        }

        $usuarioId   = (int)Session::user()['id'];
        $importados  = 0;
        $omitidosDup = 0;
        $errores     = [];

        foreach ($pedidos as $numero => $filas) {
            $notas = 'Importado ERP anterior | Pedido #' . $numero;
            if (isset($yaImportados[$notas])) { $omitidosDup++; continue; }

            $fecha = self::_parseFechaHora(trim($filas[0]['Fecha'] ?? ''), trim($filas[0]['Hora'] ?? ''));
            if (!$fecha) { $errores[] = "Pedido #{$numero}: fecha inválida ('" . trim($filas[0]['Fecha'] ?? '') . "')."; continue; }

            $detalleDB      = [];
            $subtotal       = 0.0;
            $totalIva       = 0.0;
            $totalConIva    = 0.0;
            $hayError       = false;
            $itemsFaltantes = [];

            foreach ($filas as $row) {
                $nombre   = trim($row['Producto'] ?? '');
                $cantidad = ImportHelper::toFloat($row['Cantidad'] ?? '0');
                $total    = ImportHelper::toFloat($row['Total'] ?? '0');
                if ($nombre === '' || $cantidad <= 0) continue;

                $key = self::_norm($nombre);
                if (!isset($prodIdx[$key])) {
                    $hayError = true;
                    $itemsFaltantes[] = $nombre;
                    continue;
                }

                $prod       = $prodIdx[$key];
                $ivaPct     = (float)$prod['iva'];
                $precioUnit = round($total / $cantidad, 4);
                $subIva     = $total;
                $neto       = round($subIva / (1 + $ivaPct / 100), 4);
                $iva        = round($subIva - $neto, 4);

                $subtotal    += $neto;
                $totalIva    += $iva;
                $totalConIva += $subIva;

                $detalleDB[] = [
                    'producto_id'      => (int)$prod['id'],
                    'cantidad'         => $cantidad,
                    'precio_unitario'  => $precioUnit,
                    'descuento_pct'    => 0,
                    'subtotal'         => $neto,
                    'iva_porcentaje'   => $ivaPct,
                    'subtotal_con_iva' => $subIva,
                ];
            }

            if ($hayError) {
                $errores[] = "Pedido #{$numero}: producto(s) sin coincidencia (" . implode(', ', array_unique($itemsFaltantes)) . ") — omitido.";
                continue;
            }
            if (empty($detalleDB)) continue;

            $cabecera = [
                'usuario_id'      => $usuarioId,
                'turno_id'        => null,
                'cliente_id'      => null,
                'subtotal'        => round($subtotal, 2),
                'total_iva'       => round($totalIva, 2),
                'total_con_iva'   => round($totalConIva, 2),
                'descuento_global'=> 0,
                'metodo_pago'     => 'efectivo',
                'facturar_afip'   => 0,
                'estado'          => 'completada',
                'mesa'            => null,
                'notas'           => $notas,
                'eliminado'       => 0,
                'created_at'      => $fecha,
                'updated_at'      => $fecha,
            ];

            try {
                $this->model->registrarImportada($cabecera, $detalleDB);
                $importados++;
            } catch (\Throwable $e) {
                $errores[] = "Pedido #{$numero}: " . $e->getMessage();
            }
        }

        $totalErrores = count($errores);
        $erroresOut   = array_slice($errores, 0, 50);
        if ($totalErrores > 50) $erroresOut[] = '… y ' . ($totalErrores - 50) . ' error(es) más.';

        $advertencias = [];
        if ($omitidosDup > 0) {
            $advertencias[] = "{$omitidosDup} pedido(s) ya habían sido importados anteriormente y se omitieron.";
        }

        $this->json([
            'ok'           => true,
            'importados'   => $importados,
            'total'        => count($pedidos),
            'errores'      => $erroresOut,
            'advertencias' => $advertencias,
        ]);
    }

    /** Descarga un CSV de referencia con las columnas esperadas del archivo a importar */
    public function plantilla(): void
    {
        $this->requireAuth();
        require_once BASE_PATH . '/core/ImportHelper.php';

        $columnas = ['Fecha', 'Dia', 'Mes', 'Hora', 'Numero', 'Codigo', 'Producto', 'Cantidad', 'Total', 'Rubro', 'SubRubro', 'Area', 'Sector'];
        $ejemplos = [
            ['25-09-2024 17:07', 'miércoles', 'sep.', '17', '2', '83', 'PROMO HELADO Dos 1/4kg', '1', '5700', 'Promos', 'Heladeria', 'Mostrador', ''],
            ['25-09-2024 17:07', 'miércoles', 'sep.', '17', '2', '44', 'COCA COLA', '1', '2350', 'Bebidas', 'Sin alcohol', 'Mostrador', ''],
        ];

        ImportHelper::sendCsvTemplate('plantilla_ventas_historico.csv', $columnas, $ejemplos);
    }

    /** Lee y valida el archivo subido para importar ventas. Devuelve [rows, error]. */
    private function _leerArchivoImport(): array
    {
        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            return [null, 'No se recibió ningún archivo.'];
        }
        try {
            $rows = ImportHelper::parseUploadedFile($_FILES['archivo']);
        } catch (\Exception $e) {
            return [null, $e->getMessage()];
        }
        if (empty($rows)) {
            return [null, 'El archivo está vacío o sin datos.'];
        }

        $requeridas = ['Fecha', 'Numero', 'Producto', 'Cantidad', 'Total'];
        $faltantes  = array_diff($requeridas, array_keys($rows[0]));
        if ($faltantes) {
            return [null, 'El archivo no tiene el formato esperado. Faltan columnas: ' . implode(', ', $faltantes) . '.'];
        }

        return [$rows, null];
    }

    /** Normaliza un string para comparación: minúsculas + espacios internos colapsados */
    private static function _norm(string $s): string
    {
        $s = trim($s);
        if (mb_detect_encoding($s, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true) !== 'UTF-8') {
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        }
        $s = mb_strtolower($s, 'UTF-8');
        return preg_replace('/\s+/', ' ', $s);
    }

    /**
     * Convierte la fecha del ERP anterior a "YYYY-MM-DD HH:MM:00".
     * Acepta "dd-mm-YYYY HH:MM" o "dd/mm/YYYY" (con o sin hora, separador '-' o '/').
     * Si la fecha no trae hora, usa $horaFallback (columna "Hora" del archivo) o 00.
     */
    private static function _parseFechaHora(string $v, string $horaFallback = ''): ?string
    {
        if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})(?:[ T](\d{1,2}):(\d{2}))?$#', trim($v), $m)) {
            $hora = isset($m[4]) ? (int)$m[4] : (int)$horaFallback;
            $min  = isset($m[5]) ? (int)$m[5] : 0;
            return sprintf('%04d-%02d-%02d %02d:%02d:00', (int)$m[3], (int)$m[2], (int)$m[1], $hora, $min);
        }
        return null;
    }
}
