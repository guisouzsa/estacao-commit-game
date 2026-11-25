import { cardImageUrl, cardMeta } from '@/Components/GameCard';
import { motion } from 'framer-motion';

export type Flight = {
    id: string;
    card: string;
    from: DOMRect;
    to: DOMRect;
    /** Giro final (para ficar de frente para quem está naquele lado da mesa). */
    rotate: number;
    /** Fica parada no destino até ser removida (jogada aguardando o servidor confirmar). */
    hold: boolean;
    /** Carta de outro jogador: sai de um painel (menor) em vez da mão. */
    small?: boolean;
    /** Espaço do tabuleiro (`jogador:passo`) que fica vazio até a carta chegar. */
    slotKey?: string;
    /** Chamado quando a carta chega ao destino (ex.: mostrar a carta real da mão). */
    onLand?: () => void;
    /**
     * Chamado no instante da chegada, ANTES de a carta em voo sair: a carta real aparece por baixo
     * (fade) e a em voo some depois, sem piscar nem buraco entre as duas.
     */
    onArrive?: () => void;
    /** Anel de destaque (carta que ENTRA na mão). */
    glow?: boolean;
    /** Duração do voo, em segundos (padrão 0,42). */
    duration?: number;
};

const HANDOFF_MS = 170; // tempo que a carta em voo fica sobre a real enquanto esta aparece

const FROM_W = 96; // largura base da carta em voo

/**
 * Camada de cartas em voo: representações TEMPORÁRIAS (uma por jogada), só com transform e
 * opacity. Nunca guardam estado de jogo: quem manda é o servidor. Ao terminar, saem da tela.
 */
export default function FlightLayer({
    flights,
    onDone,
}: {
    flights: Flight[];
    onDone: (id: string) => void;
}) {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none fixed inset-0 z-[45]"
        >
            {flights.map((flight) => {
                const art = cardImageUrl(flight.card);
                const fromW = flight.from.width || FROM_W;
                const fromH = flight.from.height || fromW * (88 / 63);
                const sideways = Math.abs(flight.rotate) === 90;
                const targetW = sideways ? flight.to.height : flight.to.width;
                const scale = Math.max(0.15, targetW / fromW);
                const dx =
                    flight.to.left +
                    flight.to.width / 2 -
                    (flight.from.left + fromW / 2);
                const dy =
                    flight.to.top +
                    flight.to.height / 2 -
                    (flight.from.top + fromH / 2);

                return (
                    <motion.div
                        key={flight.id}
                        className="absolute overflow-hidden rounded-xl border-[3px] border-[#1d1d1b] bg-[#f3f1ec]"
                        style={{
                            left: flight.from.left,
                            top: flight.from.top,
                            width: fromW,
                            height: fromH,
                            filter: 'drop-shadow(0 10px 8px #0008)',
                            boxShadow: flight.glow
                                ? '0 0 0 3px #b4da33, 0 0 18px #b4da33aa'
                                : undefined,
                        }}
                        initial={{
                            x: 0,
                            y: 0,
                            scale: flight.small ? 0.5 : 1,
                            rotate: 0,
                            opacity: 1,
                        }}
                        animate={{
                            x: dx,
                            y: dy,
                            scale,
                            rotate: flight.rotate,
                            opacity: 1,
                        }}
                        transition={{
                            duration: flight.duration ?? 0.42,
                            ease: [0.22, 0.9, 0.3, 1],
                        }}
                        onAnimationComplete={() => {
                            if (flight.hold) return;
                            if (flight.onArrive) {
                                flight.onArrive();
                                window.setTimeout(() => onDone(flight.id), HANDOFF_MS);
                            } else {
                                onDone(flight.id);
                            }
                        }}
                    >
                        {art ? (
                            <img
                                alt=""
                                className="size-full object-cover"
                                draggable={false}
                                src={art}
                            />
                        ) : (
                            <span
                                className="grid size-full place-items-center font-black text-white"
                                style={{
                                    background: `linear-gradient(145deg, ${cardMeta(flight.card).tone}, #1d1d1b)`,
                                }}
                            >
                                {cardMeta(flight.card).label}
                            </span>
                        )}
                    </motion.div>
                );
            })}
        </div>
    );
}
