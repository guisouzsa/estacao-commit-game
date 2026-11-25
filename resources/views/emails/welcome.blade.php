<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Bem-vindo à Estação Commit</title>
</head>
<body style="margin:0;padding:0;background-color:#f1efe9;">
{{-- Texto de pré-visualização (aparece ao lado do assunto na caixa de entrada) --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#f1efe9;">
    @if ($google)
        Sua conta foi criada com o Google. Personalize seu perfil, jogue e suba no ranking.
    @else
        Seu cadastro com e-mail foi concluído. Personalize seu perfil, jogue e suba no ranking.
    @endif
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
        <tr><td align="center" style="background-color:#ffffff;padding:36px 28px 30px;border:3px solid #1d1d1b;border-top:0;border-bottom:0;font-family:Arial,Helvetica,sans-serif;color:#1d1d1b;">
            <div style="font-size:12px;line-height:16px;font-weight:bold;letter-spacing:2px;color:#464498;text-transform:uppercase;">
                {{ $google ? 'Cadastro com Google' : 'Cadastro com e-mail' }}
            </div>
            <h1 style="margin:10px 0 0;font-size:26px;line-height:32px;font-weight:900;color:#1d1d1b;">Bem-vindo, {{ '@'.$username }}!</h1>
            <p style="margin:14px 0 0;font-size:15px;line-height:23px;color:#555555;">
                @if ($google)
                    Sua conta na Estação Commit foi criada com a sua <strong style="color:#1d1d1b;">conta Google</strong>.
                    Para voltar, é só usar <strong style="color:#1d1d1b;">Continuar com Google</strong> na tela de entrada.
                @else
                    Você se cadastrou na Estação Commit com o seu <strong style="color:#1d1d1b;">e-mail</strong>.
                    Para voltar, entre com este e-mail e a senha que você criou.
                @endif
            </p>

            {{-- Botão --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:26px auto 0;">
            <tr><td align="center" style="background-color:#b4da33;border:3px solid #1d1d1b;">
                <a href="{{ $dashboard }}" style="display:inline-block;padding:14px 26px;font-family:'Courier New',Courier,monospace;font-size:15px;line-height:18px;font-weight:bold;letter-spacing:1px;color:#1d1d1b;text-decoration:none;text-transform:uppercase;">Entrar na estação</a>
            </td></tr>
            </table>
        </td></tr>

        {{-- O que fazer agora --}}
        <tr><td style="background-color:#faf9f5;padding:24px 28px;border:3px solid #1d1d1b;border-top:2px dashed #cfcabd;border-bottom:0;font-family:Arial,Helvetica,sans-serif;color:#1d1d1b;">
            <div style="font-size:12px;line-height:16px;font-weight:bold;letter-spacing:2px;color:#464498;text-transform:uppercase;">Aproveite a estação</div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:12px;">
                @foreach ([
                    ['Personalize seu perfil', 'Escolha avatar, capa, moldura, título e as conquistas em destaque.'],
                    ['Jogue partidas', 'Crie uma sala, chame amigos pelo código ou complete a mesa com bots.'],
                    ['Faça parte do ranking', 'Cada vitória conta no ranking e coloca você entre os melhores da estação.'],
                    ['Desbloqueie conquistas', 'Insígnias de bronze a lendária aparecem conforme você joga.'],
                ] as $i => [$title, $text])
                <tr>
                    <td width="34" valign="top" style="padding:8px 0;">
                        <div style="width:24px;height:24px;line-height:24px;text-align:center;background-color:#1d1d1b;color:#b4da33;font-family:'Courier New',Courier,monospace;font-size:13px;font-weight:bold;">{{ $i + 1 }}</div>
                    </td>
                    <td valign="top" style="padding:8px 0;font-size:14px;line-height:20px;color:#555555;">
                        <strong style="color:#1d1d1b;">{{ $title }}</strong><br>{{ $text }}
                    </td>
                </tr>
                @endforeach
            </table>
            <p style="margin:12px 0 0;font-size:13px;line-height:20px;color:#666666;">
                Comece pelo seu perfil: <a href="{{ $profile }}" style="color:#464498;font-weight:bold;">personalizar agora</a>.
            </p>
        </td></tr>

        {{-- Aviso de segurança --}}
        <tr><td align="center" style="background-color:#ffffff;padding:18px 28px;border:3px solid #1d1d1b;border-top:2px dashed #cfcabd;border-bottom:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;color:#666666;">
            <strong style="color:#1d1d1b;">Não reconhece este cadastro?</strong>
            @if ($google)
                Revise o acesso de apps na sua conta Google.
            @else
                Use “Esqueci minha senha” na tela de entrada para recuperar o acesso.
            @endif
            A Estação Commit nunca pede sua senha por e-mail.
        </td></tr>

        {{-- Rodapé --}}
        <tr><td align="center" style="background-color:#1d1d1b;padding:18px 24px;border:3px solid #1d1d1b;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#b9b9b2;">
            Estação Commit · aprenda Git jogando<br>
            <a href="{{ $url }}" style="color:#b4da33;text-decoration:none;font-weight:bold;">{{ $host }}</a>
        </td></tr>

    </table>

    <div style="max-width:520px;padding-top:14px;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:16px;color:#8a877d;text-align:center;">
        Você recebeu esta mensagem uma única vez porque uma conta foi criada com este e-mail na Estação Commit.
    </div>

</td></tr>
</table>
</body>
</html>
