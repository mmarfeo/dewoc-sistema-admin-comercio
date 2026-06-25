<?php

class Mailer
{
    public static function send(string $to, string $toName, string $subject, string $htmlBody): bool
    {
        if (!MAIL_ENABLED) {
            logApp("Email (deshabilitado). Para: {$to} | Asunto: {$subject}");
            return false;
        }

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\n";
        $headers .= 'Reply-To: ' . MAIL_FROM . "\r\n";
        $headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";

        $toFormatted = $toName ? "{$toName} <{$to}>" : $to;
        $result      = @mail($toFormatted, $subject, $htmlBody, $headers);

        if (!$result) {
            logApp("Error al enviar email a {$to}: {$subject}", 'ERROR');
        }

        return (bool)$result;
    }

    public static function confirmacionPedido(array $pedido): bool
    {
        $email = trim($pedido['cliente_email'] ?? '');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $nombre  = $pedido['cliente_nombre'] ?? 'Cliente';
        $id      = (int)$pedido['id'];
        $total   = number_format((float)($pedido['total_con_iva'] ?? 0), 2, ',', '.');
        $tienda  = TIENDA_NOMBRE;
        $subject = "Confirmación de pedido #{$id} — {$tienda}";

        $nombreEsc = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $tiendaEsc = htmlspecialchars($tienda, ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Confirmación de pedido</title></head>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px">
  <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
    <div style="background:#0d6efd;padding:32px;text-align:center">
      <h1 style="color:#fff;margin:0;font-size:22px">¡Pago confirmado!</h1>
      <p style="color:rgba(255,255,255,.85);margin:8px 0 0">Gracias por tu compra en ' . $tiendaEsc . '</p>
    </div>
    <div style="padding:32px">
      <p>Hola <strong>' . $nombreEsc . '</strong>,</p>
      <p>Tu pago fue procesado correctamente. A continuación el resumen de tu pedido:</p>
      <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:14px">
        <tr style="background:#f8f9fa">
          <td style="padding:12px 16px;border:1px solid #dee2e6;font-weight:600">Número de pedido</td>
          <td style="padding:12px 16px;border:1px solid #dee2e6">#' . $id . '</td>
        </tr>
        <tr>
          <td style="padding:12px 16px;border:1px solid #dee2e6;font-weight:600">Total pagado</td>
          <td style="padding:12px 16px;border:1px solid #dee2e6;font-weight:700;color:#198754">$' . $total . '</td>
        </tr>
      </table>
      <p>Nos pondremos en contacto para coordinar la entrega.</p>
      <p style="color:#6c757d;font-size:13px">Si tenés alguna duda, respondé este email.</p>
    </div>
    <div style="background:#f8f9fa;padding:16px;text-align:center;border-top:1px solid #dee2e6">
      <p style="margin:0;color:#6c757d;font-size:12px">Este mensaje fue generado automáticamente por ' . $tiendaEsc . '</p>
    </div>
  </div>
</body>
</html>';

        return self::send($email, $nombre, $subject, $html);
    }

    public static function resetPassword(string $email, string $nombre, string $resetUrl): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        $subject   = 'Restablecer contraseña — ' . APP_NAME;
        $nombreEsc = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $urlEsc    = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $appEsc    = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Restablecer contraseña</title></head>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px">
  <div style="max-width:580px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
    <div style="background:#0d6efd;padding:32px;text-align:center">
      <h1 style="color:#fff;margin:0;font-size:20px">Restablecer contraseña</h1>
    </div>
    <div style="padding:32px">
      <p>Hola <strong>' . $nombreEsc . '</strong>,</p>
      <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong>' . $appEsc . '</strong>.</p>
      <p>Hacé clic en el botón para crear una nueva contraseña. El enlace es válido durante <strong>1 hora</strong>.</p>
      <div style="text-align:center;margin:28px 0">
        <a href="' . $urlEsc . '" style="background:#0d6efd;color:#fff;padding:13px 28px;border-radius:6px;text-decoration:none;font-weight:600;font-size:15px">
          Restablecer contraseña
        </a>
      </div>
      <p style="color:#6c757d;font-size:13px">Si no solicitaste este cambio, ignorá este email. Tu contraseña no cambiará.</p>
      <p style="color:#6c757d;font-size:12px;word-break:break-all">Enlace directo: <a href="' . $urlEsc . '">' . $urlEsc . '</a></p>
    </div>
    <div style="background:#f8f9fa;padding:16px;text-align:center;border-top:1px solid #dee2e6">
      <p style="margin:0;color:#6c757d;font-size:12px">Este mensaje fue generado automáticamente por ' . $appEsc . '</p>
    </div>
  </div>
</body>
</html>';

        return self::send($email, $nombre, $subject, $html);
    }
}
