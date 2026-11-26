// Checagem de imagem imprópria (+18) ANTES de enviar foto ou capa de perfil.
//
// Roda no navegador com o modelo aberto NSFWJS (MobileNetV2, ~90% de acerto segundo os autores), carregado
// só quando alguém escolhe uma imagem: quem não envia foto não baixa nada. A imagem não sai do aparelho
// para ser analisada.
//
// Limite honesto: como roda no navegador, alguém com conhecimento técnico consegue pular a checagem. Ela
// barra o caso comum; para garantia total seria preciso analisar também no servidor.

/** Probabilidades por classe do modelo: Drawing, Hentai, Neutral, Porn, Sexy (0 a 1). */
export type SafetyScores = Partial<Record<'Drawing' | 'Hentai' | 'Neutral' | 'Porn' | 'Sexy', number>>;

export type SafetyVerdict = {
    /** true = pode usar. */
    allowed: boolean;
    /** false quando a checagem não pôde rodar (navegador sem suporte, falha ao carregar). */
    checked: boolean;
    scores?: SafetyScores;
};

/**
 * Limites (rígidos, de propósito): pornografia ou hentai a partir de 50%, conteúdo sexy a partir de 60%,
 * ou a soma dos três a partir de 75%.
 */
export const LIMITS = { porn: 0.5, hentai: 0.5, sexy: 0.6, combined: 0.75 } as const;

/** Decide a partir das probabilidades (função pura, testada em tests/js). */
export function judge(scores: SafetyScores): boolean {
    const porn = scores.Porn ?? 0;
    const hentai = scores.Hentai ?? 0;
    const sexy = scores.Sexy ?? 0;

    return !(
        porn >= LIMITS.porn ||
        hentai >= LIMITS.hentai ||
        sexy >= LIMITS.sexy ||
        porn + hentai + sexy >= LIMITS.combined
    );
}

type Classifier = {
    classify: (
        image: HTMLImageElement,
        top?: number,
    ) => Promise<{ className: string; probability: number }[]>;
};

let classifier: Promise<Classifier> | null = null;

/** Carrega o TensorFlow.js e o modelo uma única vez por página (os arquivos ficam no cache do navegador). */
function loadClassifier(): Promise<Classifier> {
    classifier ??= (async () => {
        const tf = await import('@tensorflow/tfjs');
        tf.enableProdMode();
        await tf.ready();
        const [{ load }, { MobileNetV2Model }] = await Promise.all([
            import('nsfwjs/core'),
            import('nsfwjs/models/mobilenet_v2'),
        ]);

        return (await load('MobileNetV2', {
            modelDefinitions: [MobileNetV2Model],
        })) as unknown as Classifier;
    })().catch((error) => {
        classifier = null; // tenta de novo na próxima imagem
        throw error;
    });

    return classifier;
}

function readImage(file: Blob): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('imagem ilegível'));
        };
        image.src = url;
    });
}

/**
 * Analisa a imagem. Se a checagem não conseguir rodar, a imagem passa (checked: false) para não impedir
 * quem tem um aparelho antigo de usar o site; o servidor continua validando formato e tamanho.
 */
export async function checkImage(file: Blob): Promise<SafetyVerdict> {
    try {
        const [model, image] = await Promise.all([
            loadClassifier(),
            readImage(file),
        ]);
        const predictions = await model.classify(image, 5);
        const scores: SafetyScores = {};
        for (const { className, probability } of predictions) {
            scores[className as keyof SafetyScores] = probability;
        }

        return { allowed: judge(scores), checked: true, scores };
    } catch (error) {
        console.warn('Checagem de imagem indisponível:', error);

        return { allowed: true, checked: false };
    }
}
