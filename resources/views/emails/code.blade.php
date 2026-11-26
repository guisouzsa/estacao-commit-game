<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Seu código da Estação Commit</title>
</head>
<body style="margin:0;padding:0;background-color:#f1efe9;">
{{-- Texto de pré-visualização (aparece ao lado do assunto na caixa de entrada) --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#f1efe9;">
    Seu código de verificação é {{ $code }}. Ele vale por {{ $minutes }} minutos.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1efe9;">
<tr><td align="center" style="padding:32px 16px;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">

        {{-- Cabeçalho com a marca --}}
        <tr><td align="center" style="background-color:#464498;padding:28px 24px 22px;border:3px solid #1d1d1b;border-bottom:0;">
            <img src="{{ $logo }}" width="88" alt="Estação Commit" style="display:block;margin:0 auto 12px;width:88px;height:auto;border:0;">
            <div style="font-family:Arial,Helvetica,sans-serif;font-size:20px;line-height:22px;font-weight:900;letter-spacing:1px;color:#ffffff;">
                ESTAÇÃO <span style="color:#b4da33;">COMMIT</span>
            </div>
        </td></tr>

        {{-- Corpo --}}
        <tr><td align="center" style="background-color:#ffffff;padding:36px 28px 32px;border:3px solid #1d1d1b;border-top:0;border-bottom:0;font-family:Arial,Helvetica,sans-serif;color:#1d1d1b;">
            <div style="font-size:12px;line-height:16px;font-weight:bold;letter-spacing:2px;color:#464498;text-transform:uppercase;">Verificação de e-mail</div>
            <h1 style="margin:10px 0 0;font-size:26px;line-height:32px;font-weight:900;color:#1d1d1b;">Confirme seu e-mail</h1>
            <p style="margin:14px 0 0;font-size:15px;line-height:23px;color:#555555;">
                Use o código abaixo na tela de verificação para concluir seu cadastro e entrar na estação.
            </p>

            {{-- Código --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:28px auto 0;">
            <tr><td align="center" style="background-color:#b4da33;border:3px solid #1d1d1b;padding:16px 26px;">
                <span style="font-family:'Courier New',Courier,monospace;font-size:38px;line-height:44px;font-weight:bold;letter-spacing:10px;color:#1d1d1b;padding-left:10px;">{{ $code }}</span>
            </td></tr>
            </table>

            <p style="margin:22px 0 0;font-size:14px;line-height:21px;color:#555555;">
                Este código vale por <strong style="color:#1d1d1b;">{{ $minutes }} minutos</strong> e só pode ser usado por você.
            </p>
        </td></tr>

        {{-- Aviso de segurança --}}
        <tr><td align="center" style="background-color:#faf9f5;padding:20px 28px;border:3px solid #1d1d1b;border-top:2px dashed #cfcabd;border-bottom:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;color:#666666;">
            <strong style="color:#1d1d1b;">Não foi você?</strong> Ignore este e-mail: nenhuma conta será criada sem o código.
            Nunca compartilhe este código com ninguém.
        </td></tr>

        {{-- Rodapé --}}
        <tr><td align="center" style="background-color:#1d1d1b;padding:18px 24px;border:3px solid #1d1d1b;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#b9b9b2;">
            Estação Commit · aprenda Git jogando<br>
            <a href="{{ $url }}" style="color:#b4da33;text-decoration:none;font-weight:bold;">{{ $host }}</a>
        </td></tr>

    </table>

    <div style="max-width:520px;padding-top:14px;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:16px;color:#8a877d;text-align:center;">
        Você recebeu esta mensagem porque este e-mail foi usado em um cadastro na Estação Commit.
    </div>

</td></tr>
</table>
</body>
</html>
