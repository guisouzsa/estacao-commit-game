// Quantas páginas a pessoa já visitou DENTRO do site nesta aba (navegação do Inertia não muda document.referrer).
// O botão "Voltar" usa isso para decidir entre voltar uma tela ou ir para um destino seguro.

let visits = 0;

/** Chamado uma vez na abertura do app (resources/js/app.tsx). */
export function trackInAppNavigation(on: (event: 'navigate', callback: () => void) => unknown): void {
    on('navigate', () => {
        visits += 1;
    });
}

/** true quando existe uma tela anterior do próprio site para voltar. */
export function hasInAppHistory(): boolean {
    return visits > 1;
}
