ESTAÇÃO COMMIT - {{ $google ? 'Cadastro com Google' : 'Cadastro com e-mail' }}

Bem-vindo, {{ '@'.$username }}!

@if ($google)
Sua conta foi criada com a sua conta Google. Para voltar, use "Continuar com Google" na tela de entrada.
@else
Você se cadastrou com o seu e-mail. Para voltar, entre com este e-mail e a senha que você criou.
@endif

Aproveite a estação:
1. Personalize seu perfil: avatar, capa, moldura, título e conquistas em destaque.
2. Jogue partidas: crie uma sala, chame amigos pelo código ou complete a mesa com bots.
3. Faça parte do ranking: cada vitória conta.
4. Desbloqueie conquistas: insígnias de bronze a lendária.

Entrar na estação: {{ $dashboard }}
Personalizar o perfil: {{ $profile }}

@if ($google)
Não reconhece este cadastro? Revise o acesso de apps na sua conta Google.
@else
Não reconhece este cadastro? Use "Esqueci minha senha" na tela de entrada.
@endif
A Estação Commit nunca pede sua senha por e-mail.

{{ $url }}
