<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Autenticação com Google

O login social usa Laravel Socialite e exige credenciais OAuth próprias. No Google Cloud Console, configure um cliente OAuth 2.0 do tipo aplicação Web e adicione a URL de retorno autorizada:

```text
http://127.0.0.1:8001/auth/google/callback
```

Configure `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET` no `.env`. `GOOGLE_REDIRECT_URI` deve corresponder exatamente à URL de retorno cadastrada no Google; seu valor padrão está em `.env.example`. Depois, execute `php artisan migrate` e reinicie o servidor Laravel.

Novos usuários Google escolhem o próprio username; ele não é gerado automaticamente. Um e-mail já cadastrado não vincula contas sozinho: entre primeiro na conta existente e use “Vincular com Google” no perfil. Contas criadas pelo Google podem definir uma senha pela recuperação de senha para manter uma forma alternativa de acesso.

## Laravel Reverb no Render

O Docker Web Service executa Apache e Laravel na porta `$PORT` do Render e Laravel Reverb em `127.0.0.1:8080`. Apache encaminha somente o endpoint WebSocket `/app/` para o Reverb. O navegador usa HTTPS/WSS no hostname público do serviço; não exponha a porta interna `8080`.

No ambiente do serviço Render, configure:

| Variável | Valor/finalidade |
| --- | --- |
| `BROADCAST_CONNECTION` | `reverb` |
| `REVERB_APP_ID` | ID do app Reverb existente |
| `REVERB_APP_KEY` | Chave pública do app Reverb |
| `REVERB_APP_SECRET` | Segredo do mesmo app; mantenha somente no backend |
| `REVERB_HOST` | `127.0.0.1`, destino interno dos broadcasts Laravel |
| `REVERB_PORT` | `8080`, porta interna do Reverb |
| `REVERB_SCHEME` | `http`, protocolo interno Laravel → Reverb |
| `REVERB_SERVER_HOST` | `127.0.0.1`, endereço de bind interno do Reverb |
| `REVERB_SERVER_PORT` | `8080`, porta interna do Reverb |

Configure também as variáveis públicas abaixo no mesmo serviço. O Render as disponibiliza como build arguments para o Docker; salve as alterações usando **Save, rebuild, and deploy**, pois o Vite incorpora esses valores ao bundle:

| Variável | Valor/finalidade |
| --- | --- |
| `VITE_REVERB_APP_KEY` | A mesma chave pública `REVERB_APP_KEY` |
| `VITE_REVERB_HOST` | Hostname público real do serviço, sem `https://` (por exemplo, o domínio `.onrender.com` mostrado no Dashboard) |
| `VITE_REVERB_PORT` | `443` |
| `VITE_REVERB_SCHEME` | `https` |

Nunca defina `REVERB_APP_SECRET` como `VITE_*`. O limite inicial do Reverb é 25 conexões e o Apache aceita até 2 requisições simultâneas; são limites conservadores para o serviço de 512 MB, não uma garantia de capacidade. Acompanhe memória e erros no Render antes de ajustar esses limites. O plano gratuito pode suspender o serviço após inatividade; clientes Echo reconectam, enquanto o polling da sala segue disponível.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
