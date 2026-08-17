<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso ao Ganttist</title>
</head>
<body style="margin:0;background:#f4f5f8;color:#171a2b;font-family:Arial,sans-serif;line-height:1.5">
    <div style="max-width:560px;margin:40px auto;padding:32px;background:#fff;border-radius:16px">
        <h1 style="margin:0 0 16px;font-size:24px">Acesso ao Ganttist</h1>
        <p>Use o botão abaixo para entrar na sua conta. Este link expira em 15 minutos e só pode ser usado uma vez.</p>
        <p style="margin:28px 0">
            <a href="{{ $url }}" style="display:inline-block;padding:13px 20px;background:#6557f5;color:#fff;text-decoration:none;border-radius:8px;font-weight:700">Entrar no Ganttist</a>
        </p>
        <p>Se o botão não funcionar neste dispositivo, informe este código na tela de login:</p>
        <p style="font-size:28px;letter-spacing:8px;font-weight:700;text-align:center">{{ $pin }}</p>
        <p style="font-size:13px;color:#646979">Se você não solicitou este acesso, pode ignorar esta mensagem.</p>
    </div>
</body>
</html>
