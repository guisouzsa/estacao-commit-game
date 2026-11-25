<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Conta Google vinculada</title>
</head>
<body style="margin:0;padding:0;background-color:#f1efe9;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#f1efe9;">
    Agora você também pode entrar na Estação Commit com o Google.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1efe9;">
<tr><td align="center" style="padding:32px 16px;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">

        <tr><td align="center" style="background-color:#464498;padding:28px 24px 22px;border:3px solid #1d1d1b;border-bottom:0;">
            <img src="{{ $logo }}" width="88" alt="Estação Commit" style="display:block;margin:0 auto 12px;width:88px;height:auto;border:0;">
            <div style="font-family:Arial,Helvetica,sans-serif;font-size:20px;line-height:22px;font-weight:900;letter-spacing:1px;color:#ffffff;">
                ESTAÇÃO <span style="color:#b4da33;">COMMIT</span>
            </div>
        </td></tr>

        <tr><td align="center" style="background-color:#ffffff;padding:36px 28px 30px;border:3px solid #1d1d1b;border-top:0;border-bottom:0;font-family:Arial,Helvetica,sans-serif;color:#1d1d1b;">
            <div style="font-size:12px;line-height:16px;font-weight:bold;letter-spacing:2px;color:#464498;text-transform:uppercase;">Segurança da conta</div>
            <h1 style="margin:10px 0 0;font-size:26px;line-height:32px;font-weight:900;color:#1d1d1b;">Conta Google vinculada</h1>
            <p style="margin:14px 0 0;font-size:15px;line-height:23px;color:#555555;">
                Olá, <strong style="color:#1d1d1b;">{{ '@'.$username }}</strong>!
                @if ($fromSettings)
                    Você vinculou uma conta Google ao seu perfil nas configurações.
                @else
                    Você entrou com o Google usando o mesmo e-mail da sua conta, e ela passou a aceitar esse acesso.
                @endif
            </p>
            <p style="margin:12px 0 0;font-size:15px;line-height:23px;color:#555555;">
                A partir de agora dá para entrar com <strong style="color:#1d1d1b;">Continuar com Google</strong>
                ou com o seu e-mail e senha, como antes.
            </p>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:26px auto 0;">
            <tr><td align="center" style="background-color:#b4da33;border:3px solid #1d1d1b;">
                <a href="{{ $profile }}" style="display:inline-block;padding:14px 26px;font-family:'Courier New',Courier,monospace;font-size:15px;line-height:18px;font-weight:bold;letter-spacing:1px;color:#1d1d1b;text-decoration:none;text-transform:uppercase;">Ver meu perfil</a>
            </td></tr>
            </table>
        </td></tr>

        <tr><td align="center" style="background-color:#faf9f5;padding:20px 28px;border:3px solid #1d1d1b;border-top:2px dashed #cfcabd;border-bottom:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;color:#666666;">
            <strong style="color:#1d1d1b;">Não foi você?</strong>
            Troque sua senha pelo “Esqueci minha senha” na tela de entrada e revise o acesso de apps na sua conta Google.
            A Estação Commit nunca pede sua senha por e-mail.
        </td></tr>

        <tr><td align="center" style="background-color:#1d1d1b;padding:18px 24px;border:3px solid #1d1d1b;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#b9b9b2;">
            Estação Commit · aprenda Git jogando<br>
            <a href="{{ $url }}" style="color:#b4da33;text-decoration:none;font-weight:bold;">{{ $host }}</a>
        </td></tr>

    </table>

    <div style="max-width:520px;padding-top:14px;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:16px;color:#8a877d;text-align:center;">
        Aviso automático de segurança enviado uma vez, quando o Google foi vinculado à sua conta.
    </div>

</td></tr>
</table>
</body>
</html>
