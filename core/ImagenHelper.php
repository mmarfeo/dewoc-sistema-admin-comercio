<?php
/**
 * Normalización de fotos: corrección de rotación EXIF + achicado (ver
 * docs/integracion-menuapp-checklist.md, etapa 8, tareas 8.7 y 8.10).
 *
 * Dos problemas concretos que resuelve, y que antes llegaban hasta el menú del cliente:
 *
 * 1. **Rotación.** Un celular sostenido en vertical casi nunca graba los píxeles rotados:
 *    guarda la foto apaisada y le pone un tag EXIF "Orientation" que dice cómo girarla.
 *    Muchos navegadores respetan ese tag al mostrar un <img>, pero cualquier recorte con
 *    `object-fit: cover`, cualquier reprocesado y varios optimizadores lo ignoran, así que
 *    la foto termina acostada. Acá se rota de verdad los píxeles y se descarta el EXIF.
 *
 * 2. **Peso.** Una foto de un celular actual pesa entre 3 y 12 MB, y el límite de subida
 *    del sistema son 5 MB. Achicar a 1600 px de lado mayor deja una foto de sobra para una
 *    tarjeta de menú (que se muestra a 400 px) y baja el peso a unos cientos de KB.
 *
 * El achicado del lado del navegador (ver la vista de Fotos del menú) existe para que la
 * subida no falle ni consuma datos de más con mala señal; esto es el respaldo del servidor,
 * que es el único que no se puede saltear.
 */
class ImagenHelper
{
    /** Lado mayor máximo. Suficiente para cualquier uso del menú, y liviano para subir. */
    const LADO_MAXIMO = 1600;

    /** Calidad JPEG de salida: por encima de ~85 el archivo crece sin diferencia visible. */
    const CALIDAD_JPEG = 85;

    /**
     * Lee una imagen, la endereza según su EXIF, la achica si hace falta y la guarda como
     * JPEG en $destino. Devuelve true si salió bien.
     *
     * Si la extensión GD no está disponible, copia el archivo tal cual y lo registra en el
     * log: es preferible una foto sin optimizar que un producto sin foto.
     */
    public static function normalizar(string $origen, string $destino): bool
    {
        if (!is_file($origen)) return false;

        if (!extension_loaded('gd')) {
            logApp('ImagenHelper: la extensión GD no está disponible, la foto se copia sin normalizar.', 'WARN');
            return copy($origen, $destino);
        }

        try {
            $img = self::abrir($origen);
            if (!$img) return copy($origen, $destino);

            $img = self::rotarSegunExif($img, $origen);
            $img = self::achicar($img);

            $ok = imagejpeg($img, $destino, self::CALIDAD_JPEG);
            imagedestroy($img);

            if (!$ok) return copy($origen, $destino);
            return true;
        } catch (\Throwable $e) {
            // Nunca dejar al producto sin foto por un fallo de procesamiento.
            logApp('ImagenHelper: no se pudo normalizar la foto (' . $e->getMessage() . '), se copia tal cual.', 'WARN');
            return copy($origen, $destino);
        }
    }

    /**
     * Valida un archivo subido en $_FILES y devuelve el motivo del rechazo, o null si está
     * bien. A diferencia de los chequeos que había antes, devuelve un MENSAJE: el problema
     * no era validar, era descartar la foto sin decirle nada al usuario (ver tarea 8.10).
     *
     * El tamaño se compara contra el límite de PHP y no solo contra MAX_FILE_SIZE porque
     * una foto que supera upload_max_filesize ni llega a $_FILES con error 0.
     */
    public static function motivoRechazo(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return match ((int)($file['error'] ?? -1)) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                    'La foto es demasiado grande para subirla. Probá sacarla con menos resolución.',
                UPLOAD_ERR_PARTIAL   => 'La subida se cortó antes de terminar. Probá de nuevo.',
                UPLOAD_ERR_NO_FILE   => 'No se eligió ninguna foto.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
                    'El servidor no pudo guardar la foto. Avisale al administrador.',
                default              => 'No se pudo subir la foto. Probá de nuevo.',
            };
        }

        if (($file['size'] ?? 0) > MAX_FILE_SIZE) {
            $mb = round(($file['size'] ?? 0) / 1048576, 1);
            $max = (int)(MAX_FILE_SIZE / 1048576);
            return "La foto pesa {$mb} MB y el máximo es {$max} MB. Sacala con menos resolución o elegí otra.";
        }

        $mime = @mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
        if (!in_array($mime, ALLOWED_IMAGES, true)) {
            return 'Formato no válido: la foto tiene que ser JPG, PNG o WebP.';
        }

        return null;
    }

    // ── Internos ────────────────────────────────────────────────────────────

    private static function abrir(string $ruta): \GdImage|false
    {
        $info = @getimagesize($ruta);
        if (!$info) return false;

        return match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
            IMAGETYPE_PNG  => @imagecreatefrompng($ruta),
            IMAGETYPE_WEBP => @imagecreatefromwebp($ruta),
            default        => false,
        };
    }

    /**
     * Rota los píxeles según el tag EXIF Orientation. Los valores 2, 4, 5 y 7 incluyen un
     * espejado; se tratan igual que su rotación equivalente porque en una foto de producto
     * son prácticamente inexistentes (los genera algún software de edición, no la cámara) y
     * espejar un producto sería peor que no corregirlo.
     */
    private static function rotarSegunExif(\GdImage $img, string $ruta): \GdImage
    {
        if (!extension_loaded('exif')) return $img;

        $exif = @exif_read_data($ruta);
        $o    = (int)($exif['Orientation'] ?? 1);

        $grados = match ($o) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };
        if ($grados === 0) return $img;

        $rotada = @imagerotate($img, $grados, 0);
        if (!$rotada) return $img;

        imagedestroy($img);
        return $rotada;
    }

    /** Achica proporcionalmente si el lado mayor supera LADO_MAXIMO. Nunca agranda. */
    private static function achicar(\GdImage $img): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $mayor = max($w, $h);
        if ($mayor <= self::LADO_MAXIMO) return $img;

        $escala = self::LADO_MAXIMO / $mayor;
        $nuevo  = @imagescale($img, (int)round($w * $escala), (int)round($h * $escala));
        if (!$nuevo) return $img;

        imagedestroy($img);
        return $nuevo;
    }
}
