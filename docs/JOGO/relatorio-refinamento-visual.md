# Relatório de refinamento visual - Estação Commit

## 1. Problemas encontrados

- Os recortes anteriores usavam coordenadas internas às cartas. Títulos, rodapés e partes das bordas pretas eram removidos, sobretudo em `git login` e `merge conflict`.
- A landing page repetia cantos muito arredondados em painéis, estatísticas, CTA, componentes e autenticação.
- Sombras difusas e cartões sobrepostos enfraqueciam a leitura das molduras originais.
- Os inputs tinham sombra simétrica e foco genérico, sem conexão suficiente com o sistema visual do perfil.
- A composição do hero sobrepunha três cartas em ângulos diferentes, escondendo bordas e criando hierarquia ambígua.
- Login e cadastro usavam cartas como decoração concorrente com o formulário.

## 2. Direção visual aplicada

- Paleta consolidada a partir das artes: preto `#1d1d1b`, papel `#f3f1ec`, verde-lima `#b4da33`, violeta `#464498`, rosa `#f481c6` e vermelho `#d01600`.
- Escala de raios reduzida: controles estruturados, inputs moderados e painéis apenas discretamente arredondados.
- Bordas pretas e sombras deslocadas usadas em elementos de ênfase, sem aplicação indiscriminada.
- Badges em formato de etiqueta e títulos auxiliares com barra lateral colorida, evitando cápsulas.
- Hero reorganizado em uma ilustração principal do foguete e duas cartas completas, com camadas e z-index explícitos.

## 3. Componentes modificados

- Landing page e cabeçalho.
- Layout compartilhado de autenticação.
- Login, cadastro e estados de formulário.
- Inputs, checkbox, botões secundários e modal.
- Cards de etapas, estatísticas, CTA e galeria de componentes.
- Tokens globais em `resources/css/app.css`.

## 4. Cartas corrigidas

Foram reextraídas diretamente do PDF vetorial: `git add`, `git commit`, `git push`, blocos 1 a 4, `git clone`, `git checkout -b`, `git pull`, `git login`, `limpar credenciais` e `merge conflict`.

Todas preservam a moldura externa, os cantos, título, ilustração e rodapé, com quatro pontos de margem técnica ao redor do retângulo vetorial.

## 5. Cartas ainda pendentes

- Pull Request não aparece como arte final nos PDFs atuais; o documento de jogo também a identifica como arte a criar.
- As regras indicam que a carta Git Login existente precisa de correção textual (`--global` e `user.email`). A extração preserva fielmente a arte atual, sem alterar o original.

## 6. Mascotes encontrados e recomendação

Foram localizadas três aplicações relevantes:

- Personagem junto ao `git commit`, em verde-lima.
- Personagem junto ao computador na carta `git login`, em violeta.
- Personagem dentro do foguete no centro do tabuleiro.

Recomendação: manter o nome tipográfico **Estação Commit** como logotipo principal; usar o personagem de `git login` como assinatura compacta e o foguete com personagem como ilustração de campanha, hero e autenticação. O documento oficial alerta que o gato lembra o Octocat e recomenda criar um mascote próprio antes de publicação comercial. Por isso, nenhuma das extrações deve substituir definitivamente o logotipo nem ser tratada como novo personagem autoral.

As versões transparentes são recortes técnicos derivados do material impresso. Como não há arquivo-fonte em camadas, elas são adequadas para protótipos e interface, mas devem ser revisadas pelo autor da arte antes de produção gráfica final.

## 7. Assets novos

- `public/images/estacao-commit/corrected/`: 13 cartas corrigidas.
- `public/images/estacao-commit/legacy/`: recortes anteriores preservados.
- `public/images/estacao-commit/mascots/`: três estudos transparentes.
- `public/images/estacao-commit/audit/contact-sheet-corrected.*`: folha geral.
- `public/images/estacao-commit/audit/contact-sheet-comparison.*`: comparação origem, anterior e corrigido.
- `scripts/extract_estacao_assets.py`: extração reproduzível a partir dos PDFs originais.

## 8. Verificações

- As folhas de contato foram renderizadas e inspecionadas visualmente.
- Cada uma das 13 cartas foi conferida na folha geral.
- Os três estudos de mascote foram renderizados com transparência de fundo conectada às bordas.
- Build e testes devem ser executados após cada alteração estrutural.

## 9. Pendências externas

- Criar e validar a arte oficial da Pull Request.
- Corrigir a arte-fonte de Git Login conforme o documento de regras.
- Validar direitos e diferenciação do personagem antes de publicação comercial.
- Obter os arquivos vetoriais editáveis originais para uma separação de fundo definitiva dos mascotes.
