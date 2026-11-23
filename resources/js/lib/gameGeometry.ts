// Geometria da mesa: onde cada jogador fica e onde cada carta do percurso é colocada.
// Sem dependências de React: testável com o runner do Node.

/** Mesma sequência obrigatória do servidor (GameEngine::REQUIRED). Só para rotular os espaços. */
export const PATH_SEQUENCE = [
    'git_login',
    'git_clone',
    'git_checkout',
    'block_1',
    'git_add',
    'git_commit',
    'block_2',
    'git_add',
    'git_commit',
    'block_3',
    'git_add',
    'git_commit',
    'block_4',
    'git_add',
    'git_commit',
    'git_pull',
    'git_push',
    'pull_request',
] as const;

export const PATH_LENGTH = PATH_SEQUENCE.length; // 18

/** 0 = embaixo (você), 1 = esquerda, 2 = em cima, 3 = direita. O jogo segue no sentido horário. */
export type Side = 0 | 1 | 2 | 3;

export const sideOf = (seat: number, viewerSeat: number): Side =>
    ((((seat - viewerSeat) % 4) + 4) % 4) as Side;

export type Area = {
    /** Retângulo branco do tabuleiro (em % do lado da imagem, que é quadrada). */
    left: number;
    top: number;
    width: number;
    height: number;
    cols: number;
    rows: number;
    /** Giro das cartas para ficarem "de frente" para quem está naquele lado. */
    rotate: 0 | 90 | 180 | -90;
};

// Medidas tiradas da arte original (tabuleiro.png, 1404 x 1404): as quatro áreas brancas.
export const AREAS: Record<Side, Area> = {
    0: {
        left: 31.3,
        top: 76.6,
        width: 37.3,
        height: 11.2,
        cols: 9,
        rows: 2,
        rotate: 0,
    },
    1: {
        left: 12.1,
        top: 31.3,
        width: 11.4,
        height: 37.4,
        cols: 2,
        rows: 9,
        rotate: 90,
    },
    2: {
        left: 31.3,
        top: 12.3,
        width: 37.2,
        height: 11.4,
        cols: 9,
        rows: 2,
        rotate: 180,
    },
    3: {
        left: 76.4,
        top: 31.7,
        width: 11.4,
        height: 37.2,
        cols: 2,
        rows: 9,
        rotate: -90,
    },
};

/** Coluna e linha (na tela) do passo `index` do percurso de quem está em `side`. */
export function slotCell(
    side: Side,
    index: number,
): { col: number; row: number } {
    const area = AREAS[side];
    const long = area.cols > area.rows ? area.cols : area.rows; // 9
    const along = index % long;
    const across = Math.floor(index / long);

    switch (side) {
        case 0: // lê da esquerda para a direita, linha a linha
            return { col: along, row: across };
        case 2: // do outro lado da mesa: espelhado
            return { col: long - 1 - along, row: area.rows - 1 - across };
        case 1: // lê de cima para baixo
            return { col: across, row: along };
        default:
            return { col: area.cols - 1 - across, row: long - 1 - along };
    }
}

/** Retângulo (em % do tabuleiro) do passo `index` do percurso. */
export function slotRect(side: Side, index: number) {
    const area = AREAS[side];
    const { col, row } = slotCell(side, index);
    const width = area.width / area.cols;
    const height = area.height / area.rows;

    return {
        left: area.left + col * width,
        top: area.top + row * height,
        width,
        height,
    };
}

/** Rótulo curto do passo (mostrado esmaecido no espaço vazio). */
export const STEP_LABEL: Record<string, string> = {
    git_login: 'login',
    git_clone: 'clone',
    git_checkout: 'branch',
    git_add: 'add',
    git_commit: 'commit',
    git_pull: 'pull',
    git_push: 'push',
    pull_request: 'PR',
    block_1: 'bloco 1',
    block_2: 'bloco 2',
    block_3: 'bloco 3',
    block_4: 'bloco 4',
};
