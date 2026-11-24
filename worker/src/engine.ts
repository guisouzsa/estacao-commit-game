// Motor do jogo Estação Commit em TypeScript: porte fiel de app/Services/GameEngine.php.
// Puro (sem I/O): o Durable Object cuida de persistência, alarmes e avisos.
// NÃO altera nenhuma regra: mesmas cartas, mesmo percurso, mesmos limites.

export const MAX_PARTICIPANTS = 4;

export const REQUIRED = [
  'git_login', 'git_clone', 'git_checkout',
  'block_1', 'git_add', 'git_commit',
  'block_2', 'git_add', 'git_commit',
  'block_3', 'git_add', 'git_commit',
  'block_4', 'git_add', 'git_commit',
  'git_pull', 'git_push', 'pull_request',
] as const;

const COMMON_COUNTS: Record<string, number> = {
  git_login: 4, git_clone: 7, git_checkout: 7, git_pull: 4,
  git_add: 16, git_commit: 16, git_push: 16, pull_request: 4,
  clear_credentials: 2, merge_conflict: 2,
};

const LABELS: Record<string, string> = {
  git_login: 'Git Login', git_clone: 'git clone', git_checkout: 'git checkout -b', git_pull: 'git pull',
  git_add: 'git add .', git_commit: 'git commit', git_push: 'git push', pull_request: 'Pull Request',
  clear_credentials: 'Limpar Credenciais', merge_conflict: 'Merge Conflict',
};

export type Participant = { id: number; seat: number; kind: 'user' | 'guest' | 'bot'; bot_key?: string | null };

export type PlayerState = { seat: number; hand: string[]; path: string[]; step: number; blocked: boolean; pending_discard: number };
export type HistoryItem = { id: string; participant_id?: number; message: string; turn: number; notify?: boolean };

export type MatchState = {
  status: 'active' | 'finished';
  current_seat: number;
  turn_number: number;
  version: number;
  winner_participant_id: number | null;
  finished_at: string | null;
  deck: string[];
  discard: string[];
  players: Record<string, PlayerState>;
  history: HistoryItem[];
};

export type ActionName = 'play_card' | 'play_block' | 'exchange' | 'discard_excess';
export const ACTIONS: readonly string[] = ['play_card', 'play_block', 'exchange', 'discard_excess'];
export type ActionPayload = { card?: string; target_id?: number | string | null };

/** Erro de regra: vira 422 com { campo: mensagem } (mesmos campos e textos do Laravel). */
export class RuleError extends Error {
  constructor(public field: string, message: string) {
    super(message);
  }
}

export type Rng = () => number; // [0, 1)
export const cryptoRng: Rng = () => crypto.getRandomValues(new Uint32Array(1))[0] / 2 ** 32;

const shuffle = <T>(list: T[], rng: Rng): T[] => {
  for (let i = list.length - 1; i > 0; i--) {
    const j = Math.floor(rng() * (i + 1));
    [list[i], list[j]] = [list[j], list[i]];
  }
  return list;
};

const newId = () => crypto.randomUUID();

export function createMatch(participants: Participant[], rng: Rng = cryptoRng, now = new Date()): MatchState {
  if (participants.length !== MAX_PARTICIPANTS) throw new RuleError('room', 'A partida exige exatamente quatro participantes.');

  // Regra: TODOS começam com o Git Login (uma cópia por jogador, fora do baralho) + 3 cartas comuns.
  let deck: string[] = [];
  for (const [card, quantity] of Object.entries(COMMON_COUNTS)) {
    const left = Math.max(0, quantity - (card === 'git_login' ? participants.length : 0));
    deck = deck.concat(Array(left).fill(card));
  }
  shuffle(deck, rng);

  const players: Record<string, PlayerState> = {};
  for (const p of [...participants].sort((a, b) => a.seat - b.seat)) {
    const hand = shuffle(['git_login', ...deck.splice(0, 3)], rng);
    players[String(p.id)] = { seat: p.seat, hand, path: [], step: 0, blocked: false, pending_discard: 0 };
  }

  const state: MatchState = {
    status: 'active',
    current_seat: 1 + Math.floor(rng() * MAX_PARTICIPANTS),
    turn_number: 1,
    version: 1,
    winner_participant_id: null,
    finished_at: null,
    deck,
    discard: [],
    players,
    history: [{ id: newId(), message: 'A partida começou.', turn: 1 }],
  };
  void now;
  return state;
}

/** Aplica UMA ação humana ou de bot. Lança RuleError se inválida; muta `m` só em caso de sucesso. */
export function act(m: MatchState, participantId: number, action: string, payload: ActionPayload, rng: Rng = cryptoRng, now = new Date()): void {
  if (m.status !== 'active') throw new RuleError('game', 'Esta partida já foi finalizada.');
  const key = String(participantId);
  const player = m.players[key];
  if (!player) throw new RuleError('participant', 'Jogador desconhecido.');
  if (m.current_seat !== player.seat) throw new RuleError('turn', 'É a vez de outro jogador.');
  if ((player.pending_discard ?? 0) > 0 && action !== 'discard_excess') {
    throw new RuleError('hand', 'Escolha uma carta para respeitar o limite da mão.');
  }

  // Trabalha numa cópia: uma regra violada no meio da ação não deixa o estado pela metade.
  const draft = structuredClone(m);
  const card = String(payload.card ?? '');
  switch (action) {
    case 'play_card':
      playCard(draft, participantId, card, payload.target_id != null ? String(payload.target_id) : null, rng, now);
      break;
    case 'play_block':
      playBlock(draft, participantId, rng);
      break;
    case 'exchange':
      exchange(draft, participantId, card, rng);
      break;
    case 'discard_excess':
      discardExcess(draft, participantId, card);
      break;
    default:
      throw new RuleError('action', 'Ação de jogo desconhecida.');
  }
  draft.version++;
  Object.assign(m, draft);
}

/** Bots jogam enquanto for a vez de um bot (mesmo limite de 12 jogadas do PHP). */
export function runBots(m: MatchState, participants: Participant[], rng: Rng = cryptoRng, now = new Date()): void {
  for (let guard = 0; guard < 12 && m.status === 'active'; guard++) {
    const bot = participants.find((p) => p.seat === m.current_seat && p.kind === 'bot');
    if (!bot) return;

    const player = m.players[String(bot.id)];
    let action: ActionName = 'exchange';
    let payload: ActionPayload = { card: player.hand[0] ?? '' };

    if ((player.pending_discard ?? 0) > 0) {
      action = 'discard_excess';
    } else {
      const required: string | undefined = REQUIRED[player.step];
      if (required?.startsWith('block_')) {
        action = 'play_block';
        payload = {};
      } else if (required !== undefined && player.hand.includes(required)) {
        action = 'play_card';
        payload = { card: required };
      }
    }
    act(m, bot.id, action, payload, rng, now);
  }
}

function playCard(m: MatchState, id: number, card: string, targetId: string | null, rng: Rng, now: Date): void {
  const player = m.players[String(id)];
  const index = player.hand.indexOf(card);
  if (index === -1) throw new RuleError('card', 'Essa carta não está na sua mão.');

  const required: string | undefined = REQUIRED[player.step];
  if (card === 'git_pull' && player.step < 3) {
    player.hand.splice(index, 1);
    m.discard.push(card);
    draw(m, player, 2, rng);
    history(m, id, 'usou git pull no Treinamento e comprou duas cartas.');
    finishOrAdvance(m, id);
    return;
  }

  if (card === 'clear_credentials' || card === 'merge_conflict') {
    attack(m, id, card, targetId);
    player.hand.splice(index, 1);
    m.discard.push(card);
    draw(m, player, 1, rng);
    finishOrAdvance(m, id);
    return;
  }

  if (card !== required || required.startsWith('block_')) {
    throw new RuleError('card', 'Essa carta não é a próxima etapa do seu percurso.');
  }

  player.hand.splice(index, 1);
  player.path.push(card);
  player.step++;
  history(m, id, `jogou ${label(card)}.`);

  if (card === 'pull_request') {
    m.status = 'finished';
    m.winner_participant_id = id;
    m.finished_at = now.toISOString();
    history(m, id, 'concluiu uma Pull Request válida e venceu!');
    return;
  }

  draw(m, player, 1, rng);
  finishOrAdvance(m, id);
}

function playBlock(m: MatchState, id: number, rng: Rng): void {
  const player = m.players[String(id)];
  const required: string | undefined = REQUIRED[player.step];
  if (!required?.startsWith('block_')) throw new RuleError('card', 'O próximo passo não é um Bloco de Código.');
  player.path.push(required);
  player.step++;
  history(m, id, `encaixou o ${required.replace('_', ' ')}.`);
  if (required === 'block_4') draw(m, player, 1, rng);
  finishOrAdvance(m, id);
}

function exchange(m: MatchState, id: number, card: string, rng: Rng): void {
  const player = m.players[String(id)];
  const index = player.hand.indexOf(card);
  if (index === -1) throw new RuleError('card', 'Escolha uma carta da sua mão para trocar.');
  player.hand.splice(index, 1);
  m.discard.push(card);
  draw(m, player, 1, rng);
  history(m, id, 'trocou uma carta.');
  advance(m);
}

function discardExcess(m: MatchState, id: number, card: string): void {
  const player = m.players[String(id)];
  const index = player.hand.indexOf(card);
  if (index === -1) throw new RuleError('card', 'Escolha uma carta da sua mão para descartar.');
  player.hand.splice(index, 1);
  m.discard.push(card);
  player.pending_discard = Math.max(0, player.hand.length - 6);
  if (player.pending_discard === 0) advance(m);
}

function attack(m: MatchState, id: number, card: string, targetId: string | null): void {
  if (!targetId || !m.players[targetId] || targetId === String(id)) throw new RuleError('target', 'Escolha outro jogador como alvo.');
  const attacker = m.players[String(id)];
  const target = m.players[targetId];
  if (card === 'clear_credentials') {
    if (attacker.step >= 3 || target.step >= 3) {
      throw new RuleError('target', 'Limpar Credenciais só funciona entre jogadores no Treinamento.');
    }
    m.discard.push(...target.path);
    target.path = [];
    target.step = 0;
  } else {
    if (target.blocked) throw new RuleError('target', 'Esse jogador já está resolvendo um Merge Conflict.');
    target.blocked = true;
  }
  history(m, id, `usou ${label(card)}.`);
}

function finishOrAdvance(m: MatchState, id: number): void {
  if (m.status === 'finished') return;
  const player = m.players[String(id)];
  player.pending_discard = Math.max(0, player.hand.length - 6);
  if (player.pending_discard === 0) advance(m);
}

function advance(m: MatchState): void {
  for (let i = 0; i < MAX_PARTICIPANTS; i++) {
    m.current_seat = (m.current_seat % MAX_PARTICIPANTS) + 1;
    m.turn_number++;
    const targetId = Object.keys(m.players).find((k) => m.players[k].seat === m.current_seat);
    if (targetId !== undefined && m.players[targetId].blocked) {
      m.players[targetId].blocked = false;
      m.history.push({ id: newId(), participant_id: Number(targetId), message: 'perdeu a vez por Merge Conflict.', turn: m.turn_number });
      continue;
    }
    return;
  }
}

function draw(m: MatchState, player: PlayerState, quantity: number, rng: Rng): void {
  for (let i = 0; i < quantity; i++) {
    if (m.deck.length === 0 && m.discard.length > 0) {
      m.deck = shuffle(m.discard, rng);
      m.discard = [];
    }
    if (m.deck.length === 0) return;
    player.hand.push(m.deck.shift()!);
  }
}

function history(m: MatchState, id: number, message: string): void {
  m.history.push({ id: newId(), participant_id: id, message, turn: m.turn_number });
  m.history = m.history.slice(-30);
}

const label = (card: string) => LABELS[card] ?? card;

/** Um bot assume o lugar de quem ficou parado (substituição definitiva, como no PHP). */
export function replaceWithBot(m: MatchState, participants: Participant[], botKey: string, name: string | null): Participant | null {
  const idle = participants.find((p) => p.seat === m.current_seat);
  if (!idle || idle.kind === 'bot') return null;
  idle.kind = 'bot';
  idle.bot_key = botKey;
  m.history.push({
    id: newId(),
    message: `Um bot assumiu a posição de ${name ? '@' + name : 'um jogador'} por inatividade.`,
    turn: m.turn_number,
    notify: true,
  });
  m.version++;
  return idle;
}
