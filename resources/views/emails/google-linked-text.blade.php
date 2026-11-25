ESTAÇÃO COMMIT - Segurança da conta

Conta Google vinculada

Olá, {{ '@'.$username }}!
@if ($fromSettings)
Você vinculou uma conta Google ao seu perfil nas configurações.
@else
Você entrou com o Google usando o mesmo e-mail da sua conta, e ela passou a aceitar esse acesso.
@endif

A partir de agora dá para entrar com "Continuar com Google" ou com o seu e-mail e senha, como antes.

Ver meu perfil: {{ $profile }}

Não foi você? Troque sua senha pelo "Esqueci minha senha" na tela de entrada e revise o acesso de apps na sua conta Google.
A Estação Commit nunca pede sua senha por e-mail.

{{ $url }}
