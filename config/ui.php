<?php

return [
    // true: notificações e seguidores abrem a casca da página na hora e as listas chegam logo depois
    // (props adiadas do Inertia), com esqueleto no meio tempo. false: tudo na primeira resposta.
    'defer_lists' => (bool) env('UI_DEFER_LISTS', true),
];
