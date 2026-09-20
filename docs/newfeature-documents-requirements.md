# Ganttist — Especificação de Gestão de Documentos, Derivações e Modo Offline de Leitura

**Status:** requisitos funcionais consolidados para planejamento de implementação  
**Projeto:** `rodrigogml/Ganttist`  
**Data:** 2026-09-19  
**Objetivo deste documento:** servir como handoff técnico e funcional para o agente responsável por planejar e posteriormente implementar a feature. Este documento descreve o estado desejado do produto; não é um histórico de decisões.

---

## 1. Resumo executivo

O Ganttist deve ganhar uma área de **Documentos** dentro de cada projeto.

A entidade **Documento** deve ser genérica: ela não representa apenas pranchas arquitetônicas. Um projeto deve poder conter PDFs, imagens, laudos, topografia, estudos de solo, relatórios, memoriais, planilhas, ZIPs e outros tipos de arquivo.

Os documentos:

- pertencem a um projeto;
- possuem histórico de revisões/versões;
- são organizados por **tags hierárquicas**, e não por pastas;
- podem receber múltiplas tags simultaneamente;
- podem ser documentos enviados pelo usuário ou **documentos derivados** gerados pelo sistema;
- podem ser consultados em modo offline somente para leitura quando previamente armazenados no dispositivo.

O sistema deve possuir uma infraestrutura genérica de **Derivações de Documento**.

Uma derivação é uma regra persistida que transforma um documento de origem em outro documento. O primeiro tipo de derivação implementado será **recorte de região de PDF**, preservando a qualidade vetorial do arquivo de origem.

Exemplo:

```text
Documento de origem
ARQ-001 — Planta Geral
└── R01
└── R02
└── R03 ← revisão atual

Derivação: "Pavimento Térreo"
processor = pdf.crop.region
source = ARQ-001
page = 1
rectangle = coordenadas persistidas

Documento derivado
Pavimento Térreo
└── R01 ← derivado da ARQ-001 R01
└── R02 ← derivado da ARQ-001 R02
└── R03 ← derivado da ARQ-001 R03
```

Quando uma nova revisão do documento de origem é criada, as derivações configuradas para regeneração automática devem criar automaticamente novas revisões dos respectivos documentos derivados.

A aplicação continuará sendo uma aplicação web/PWA. **Não será criado, nesta fase, um aplicativo nativo para desktop ou mobile.**

O modo offline terá uma regra fundamental:

> **Offline é estritamente modo leitura. Não existe edição offline, fila de mutações offline, sincronização bidirecional, resolução de conflitos ou posterior envio de alterações feitas sem conexão.**

Enquanto estiver offline, o usuário pode consultar apenas os dados e documentos previamente armazenados no dispositivo. Qualquer alteração em projeto, tarefa, relacionamento, documento, revisão, tag, derivação ou qualquer outro dado do Ganttist exige conexão online.

---

# 2. Contexto técnico atual do Ganttist

A implementação deve aproveitar a arquitetura existente e não criar um segundo produto independente.

A base atual do repositório utiliza:

- Laravel 12;
- PHP 8.4+;
- Vue 3;
- TypeScript;
- Pinia;
- Tailwind CSS 4;
- Vite;
- MySQL;
- filas Laravel;
- armazenamento pelo Laravel Filesystem;
- configuração para armazenamento local privado e S3;
- API JSON sob `/api/v1`;
- sessão server-side;
- PWA já iniciada por meio de `manifest.webmanifest` e Service Worker.

A arquitetura existente é descrita no projeto como um **monólito modular**. A nova funcionalidade deve preservar esse princípio.

## 2.1. Reutilização obrigatória do domínio de Projeto

Não deve ser criada uma segunda entidade paralela de “projeto documental”.

A entidade já existente `projects` continuará sendo a raiz do domínio.

A navegação conceitual do projeto passa a oferecer, no mínimo:

```text
Projeto
├── Tarefas
├── Gantt
└── Documentos
```

A área de Documentos deve operar sobre o mesmo:

- `project_id`;
- modelo de usuários;
- modelo de membros;
- permissões;
- autenticação;
- banco;
- infraestrutura de armazenamento;
- infraestrutura de filas.

## 2.2. Organização modular

A implementação documental não deve ampliar ainda mais componentes ou controllers monolíticos já grandes.

Deve ser criada uma separação explícita para o domínio documental, por exemplo:

```text
app/
  Domain/
    Documents/
  Services/
    Documents/
  Jobs/
    Documents/
  Http/
    Controllers/
      Api/
        DocumentController.php
        DocumentRevisionController.php
        DocumentDerivationController.php
        ProjectTagController.php

resources/js/
  documents/
    DocumentsWorkspace.vue
    DocumentLibrary.vue
    DocumentDetails.vue
    DocumentViewer.vue
    PdfCropEditor.vue
    TagSelector.vue
    TagManager.vue
    OfflineProjectManager.vue
```

Os nomes exatos podem ser ajustados durante o planejamento, desde que a separação modular seja mantida.

---

# 3. Terminologia canônica

Os seguintes termos devem ser usados no produto e no código sempre que possível.

## 3.1. Documento

Entidade lógica pesquisável e organizável pertencente a um projeto.

Um documento não é sinônimo de um arquivo físico específico.

Exemplo:

```text
Documento: ARQ-001 — Planta Geral
```

Esse documento pode possuir muitas revisões.

## 3.2. Revisão de Documento

Versão imutável do conteúdo binário de um documento em um determinado momento.

Exemplo:

```text
ARQ-001 — Planta Geral
R01
R02
R03
```

A revisão nova **não sobrescreve fisicamente** a revisão anterior. O histórico deve ser preservado.

## 3.3. Documento derivado

Documento normal cuja revisão é produzida automaticamente por uma regra de transformação aplicada a outro documento.

O documento derivado deve ter os mesmos recursos de um documento normal:

- nome;
- tags;
- revisão atual;
- histórico;
- busca;
- visualização;
- download;
- cache offline.

## 3.4. Derivação

Regra persistida que define como gerar um documento derivado a partir de um documento de origem.

Exemplos futuros:

```text
pdf.crop.region
pdf.extract.pages
image.crop.region
file.convert
zip.extract.entry
document.merge
```

Na primeira implementação, apenas `pdf.crop.region` precisa existir.

## 3.5. Processador de derivação

Implementação técnica responsável por executar uma derivação.

Exemplo:

```text
processor: pdf.crop.region
runtime: Python + PyMuPDF
```

## 3.6. Tag

Classificação reutilizável pertencente a um projeto.

Tags podem possuir tags-filhas e formar uma árvore de profundidade arbitrária.

## 3.7. Modo offline

Estado em que a aplicação opera exclusivamente a partir do cache local previamente disponível.

**Modo offline é somente leitura.**

---

# 4. Objetivos funcionais

A feature deve permitir:

1. armazenar documentos genéricos dentro de um projeto;
2. manter histórico de revisões de cada documento;
3. pesquisar e classificar documentos;
4. organizar documentos por múltiplas tags hierárquicas;
5. navegar pela biblioteca sem usar pastas;
6. visualizar PDFs dentro da aplicação;
7. selecionar visualmente uma ou mais regiões de um PDF;
8. transformar cada região selecionada em um documento derivado;
9. preservar conteúdo vetorial do PDF ao gerar o derivado;
10. persistir a regra usada para gerar cada derivado;
11. regenerar automaticamente derivados quando o documento de origem receber nova revisão;
12. registrar a origem exata de cada revisão derivada;
13. suportar processamento assíncrono;
14. permitir consulta offline dos dados e arquivos previamente armazenados;
15. impedir absolutamente qualquer mutação offline;
16. sinalizar claramente quando a aplicação está mostrando dados potencialmente desatualizados.

---

# 5. Não objetivos desta entrega

Não fazem parte desta fase:

- aplicativo nativo iOS;
- aplicativo nativo Android;
- aplicativo desktop nativo;
- Electron/Tauri;
- edição colaborativa offline;
- criação de tarefas offline;
- edição de tarefas offline;
- upload offline;
- criação de tags offline;
- alteração de metadados offline;
- fila de mutações offline;
- Background Sync de mutações;
- sincronização bidirecional;
- merge de alterações;
- resolução de conflitos;
- edição de PDFs;
- OCR;
- anotação de PDFs;
- assinatura digital;
- comparação gráfica automática entre revisões;
- extração inteligente de desenhos por IA;
- recorte de imagens nesta primeira versão;
- extração de ZIP nesta primeira versão;
- conversão de formatos nesta primeira versão;
- criação obrigatória de vínculos entre tarefas e documentos nesta entrega.

A arquitetura deve, entretanto, evitar bloquear futuros processadores de derivação e futuros vínculos entre tarefas e documentos.

---

# 6. Área de Documentos no projeto

## 6.1. Navegação principal

Dentro do workspace de um projeto deve existir uma superfície clara para:

```text
Tarefas | Gantt | Documentos
```

“Documentos” é uma biblioteca do projeto.

## 6.2. Biblioteca de documentos

A biblioteca deve permitir, no mínimo:

- listar documentos;
- pesquisar por nome;
- filtrar por tags;
- identificar documento normal vs. derivado;
- visualizar a revisão atual;
- visualizar status de processamento;
- abrir documento;
- fazer download;
- acessar histórico de revisões;
- alterar tags quando online;
- enviar novo documento quando online;
- enviar nova revisão quando online;
- criar derivação quando o tipo do arquivo suportar;
- visualizar origem de um documento derivado.

A interface pode ser tabela ou cartões, mas deve funcionar adequadamente em desktop e mobile web.

## 6.3. Organização sem pastas

Não deve existir uma árvore de diretórios como mecanismo funcional de organização dos documentos.

O usuário não precisa decidir “em qual pasta” um documento reside.

Os arquivos físicos podem estar organizados internamente no storage da aplicação, mas essa estrutura não é exposta como taxonomia ao usuário.

---

# 7. Documentos genéricos

## 7.1. Tipos de arquivo

O domínio `Document` deve ser independente de formato.

Um documento pode representar, por exemplo:

- PDF;
- PNG;
- JPEG;
- WEBP;
- DOCX;
- XLSX;
- CSV;
- TXT;
- ZIP;
- arquivos técnicos;
- outros binários.

Nem todo tipo precisa possuir visualizador embutido.

## 7.2. Visualização

Na primeira entrega:

- PDF: visualização interna com PDF.js;
- imagens suportadas pelo navegador: visualização interna;
- tipos não visualizáveis: metadados + download/abertura externa.

A ausência de preview não impede o arquivo de ser um documento do sistema.

## 7.3. Segurança de conteúdo

Os arquivos devem ser tratados como dados, nunca como conteúdo executável do servidor.

O backend deve:

- detectar MIME server-side;
- não confiar somente na extensão enviada pelo navegador;
- armazenar conteúdo em área privada;
- gerar nomes físicos não controlados pelo usuário;
- evitar path traversal;
- servir arquivos por endpoints autorizados ou URLs temporárias;
- aplicar as mesmas permissões do projeto antes de expor conteúdo.

---

# 8. Modelo de revisões

## 8.1. Regra principal

Adicionar uma revisão significa criar um novo registro de revisão.

Nunca significa sobrescrever silenciosamente os bytes de uma revisão existente.

## 8.2. Identificação da revisão

O rótulo de revisão deve ser texto, e não apenas inteiro.

Exemplos válidos:

```text
R00
R01
A
B
REV-C
2026-09-19
Emissão 04
```

Recomenda-se possuir também uma sequência interna monotônica para ordenação confiável.

## 8.3. Revisão atual

Cada documento deve possuir uma revisão atual.

Uma nova revisão enviada com sucesso passa a ser a revisão atual do documento.

O histórico anterior continua acessível.

## 8.4. Integridade

Cada revisão deve armazenar pelo menos:

- tamanho;
- MIME;
- checksum SHA-256;
- storage path/key;
- data de criação;
- usuário responsável pelo upload, quando aplicável;
- rótulo de revisão;
- sequência;
- status.

## 8.5. Imutabilidade

Após uma revisão ser aceita como válida, seu binário não deve ser alterado em lugar.

Correções devem gerar uma nova revisão.

---

# 9. Tags hierárquicas

## 9.1. Escopo

Tags pertencem ao projeto.

Uma tag criada no projeto deve ser reutilizável em qualquer documento daquele projeto.

## 9.2. Hierarquia

Tags podem apontar para uma tag-pai do mesmo projeto.

Exemplo:

```text
Disciplina
├── Arquitetura
├── Elétrica
└── Hidráulica
    ├── Água fria
    └── Água quente

Local
├── Térreo
├── 1º Pavimento
└── Cobertura

Elemento
├── Janelas
├── Portas
└── Escadas
```

A profundidade é conceitualmente arbitrária.

O sistema não deve codificar colunas do tipo:

```text
tag_level_1
tag_level_2
tag_level_3
```

Deve ser usado relacionamento recursivo por `parent_tag_id`.

## 9.3. Múltiplas classificações

Um documento pode possuir múltiplas tags simultaneamente.

Exemplo:

```text
Documento: Detalhe J01

Tags:
- Arquitetura
- Térreo
- Janelas
- Fachadas
```

O mesmo documento deve aparecer quando o usuário filtrar por qualquer classificação associada.

## 9.4. Criação e sugestão

Ao editar as tags de um documento:

- sugerir tags já existentes;
- permitir pesquisar;
- permitir criar nova tag quando autorizado;
- reduzir duplicações por erro de grafia;
- tratar nomes de tags de maneira consistente.

Recomenda-se unicidade por projeto + tag-pai + nome normalizado.

## 9.5. Validações

O backend deve impedir:

- tag como pai dela mesma;
- ciclos na hierarquia;
- pai pertencente a outro projeto.

---

# 10. Documentos derivados

## 10.1. Princípio de modelagem

Um documento derivado **continua sendo um Documento**.

Não deve existir um “tipo de objeto inferior” incapaz de usar recursos normais da biblioteca.

A distinção é que existe uma `DocumentDerivation` que explica sua origem.

## 10.2. Relação conceitual

```text
Source Document
      │
      │ DocumentDerivation
      ▼
Target Document
```

A derivação deve apontar para:

- documento de origem;
- documento de destino;
- processador;
- versão do processador;
- configuração;
- flag de regeneração automática;
- estado ativo/inativo.

## 10.3. Proveniência por revisão

Cada revisão gerada automaticamente deve guardar explicitamente:

- `source_revision_id`;
- `derivation_id`.

Não basta copiar apenas o texto do número da revisão.

A aplicação deve poder responder:

> “Esta revisão do documento derivado foi gerada a partir de qual revisão exata do documento de origem?”

## 10.4. Regeneração automática

A derivação deve possuir uma propriedade equivalente a:

```text
auto_regenerate = true
```

Para derivação criada pelo usuário via recorte de PDF, o padrão deve ser **ativado**.

Quando uma nova revisão do documento de origem se tornar atual:

1. localizar derivações ativas cujo `source_document_id` seja o documento atualizado;
2. selecionar as que possuem `auto_regenerate = true`;
3. criar execuções de derivação;
4. processar em fila;
5. gerar novas revisões dos documentos de destino;
6. associar cada nova revisão à nova revisão de origem;
7. herdar o rótulo de revisão de origem por padrão;
8. somente promover a nova revisão derivada para “atual” quando o processamento terminar com sucesso.

## 10.5. Falha de derivação

Se uma regeneração falhar:

- a revisão derivada anterior deve continuar disponível como última revisão válida;
- a nova tentativa deve aparecer como falha;
- o usuário deve conseguir identificar que o derivado está desatualizado em relação à origem;
- o sistema deve manter diagnóstico técnico suficiente para retry e auditoria;
- uma falha não deve corromper a revisão anterior.

## 10.6. Cadeias futuras

O desenho do banco não deve impedir, no futuro, uma derivação usar como origem um documento que também é derivado.

Se isso for habilitado, ciclos deverão ser proibidos.

Para o MVP, não é obrigatório oferecer interface para cadeias complexas.

---

# 11. Primeiro processador: recorte de região de PDF

## 11.1. Nome técnico recomendado

```text
pdf.crop.region
```

## 11.2. Experiência do usuário

Ao abrir um PDF com permissão de edição:

1. usuário entra no modo “Criar derivado”;
2. escolhe “Recortar região do PDF”;
3. visualiza a página com PDF.js;
4. desenha um retângulo;
5. pode ajustar o retângulo;
6. informa o nome do novo documento;
7. seleciona/cria tags;
8. mantém ou altera a opção “regenerar automaticamente”;
9. salva;
10. o backend cria a derivação e gera a primeira revisão do documento derivado.

A interface deve permitir selecionar **múltiplas regiões** em uma sessão e criar múltiplos documentos derivados, cada um com nome e tags próprios.

## 11.3. Renderização vs. saída

PDF.js pode rasterizar/renderizar páginas para **visualização na tela**.

Isso não significa que o PDF de saída possa ser criado a partir de screenshot.

A geração do documento derivado deve preservar a natureza vetorial sempre que o conteúdo original for vetorial.

**É proibido implementar o recorte como screenshot/canvas exportado para imagem e inserido em PDF.**

## 11.4. Coordenadas

A geometria da derivação deve ser persistida independentemente da resolução de tela.

Recomendação:

```json
{
  "page": 1,
  "rect_normalized": {
    "x": 0.142,
    "y": 0.281,
    "width": 0.438,
    "height": 0.317
  }
}
```

Valores normalizados devem ser relativos à caixa de página adotada pelo sistema.

Também pode ser persistida geometria em pontos PDF para diagnóstico, mas a representação canônica deve ser estável e reaplicável.

O planejamento deve definir explicitamente o tratamento de:

- `MediaBox`;
- `CropBox`;
- rotação da página;
- páginas em orientação paisagem;
- zoom do viewer;
- high-DPI;
- origem dos eixos;
- páginas com tamanhos diferentes.

## 11.5. Saída

O PDF derivado deve:

- ter uma página com dimensões equivalentes à região recortada;
- conter somente a região definida;
- preservar vetores, texto e imagens incorporadas sempre que possível;
- não sofrer rasterização integral;
- ser um PDF independente;
- manter qualidade adequada para zoom e impressão.

## 11.6. Processamento recomendado

Usar Python + PyMuPDF como processador especializado.

O fluxo recomendado é:

```text
Laravel
   │
   ├── cria/reconhece derivação
   ├── cria job
   ▼
Laravel Queue Worker
   │
   ├── chama processador Python
   ▼
Python + PyMuPDF
   │
   ├── abre source revision
   ├── calcula clip
   ├── cria novo PDF
   ├── importa conteúdo com clip sem rasterização total
   └── grava arquivo resultante
   ▼
Laravel
   ├── valida resultado
   ├── calcula checksum
   ├── registra target revision
   └── promove revisão atual
```

Não criar um segundo backend HTTP/FastAPI apenas para essa função nesta fase.

O Python deve ser um componente de processamento chamado por um job/controlador de infraestrutura do Laravel.

Uma opção apropriada é invocação segura por `Symfony Process` ou equivalente.

## 11.7. Segurança do processo Python

- não montar shell command com strings do usuário;
- passar IDs/caminhos validados;
- preferir argumentos estruturados ou JSON;
- usar diretório temporário exclusivo por execução;
- definir timeout;
- capturar stdout/stderr;
- remover temporários;
- não permitir path arbitrário fornecido pelo cliente;
- validar arquivo de saída antes de registrá-lo;
- fixar versão da dependência PyMuPDF.

## 11.8. Versionamento do processador

A derivação deve armazenar algo equivalente a:

```text
processor = pdf.crop.region
processor_version = 1
```

Isso permite distinguir regras antigas caso a implementação mude no futuro.

---

# 12. Modelo de dados proposto

Os nomes finais podem ser ajustados para os padrões do repositório, mas o domínio precisa representar estas relações.

## 12.1. `project_documents`

Campos recomendados:

```text
id                  ULID PK
project_id          ULID FK -> projects
name                string
kind                enum/manual|derived
current_revision_id ULID nullable
created_by_user_id  ULID nullable
created_at
updated_at
deleted_at          opcional/recomendado
```

Observações:

- `kind=manual` significa documento criado por upload;
- `kind=derived` significa documento produzido por derivação;
- ambos aparecem normalmente na biblioteca.

## 12.2. `project_document_revisions`

Campos recomendados:

```text
id                        ULID PK
document_id               ULID FK
revision_label            string
revision_sequence         unsigned integer
mime_type                 string
original_filename         string nullable
storage_disk              string
storage_key               string
size_bytes                unsigned bigint
sha256                    char(64)
status                    enum
created_by_user_id        ULID nullable
source_revision_id        ULID nullable
derivation_id             ULID nullable
created_at
updated_at
```

Status sugeridos:

```text
uploading
queued
processing
ready
failed
```

Para uma revisão manual, `source_revision_id` e `derivation_id` ficam nulos.

Para revisão automática, ambos apontam para sua proveniência.

## 12.3. `project_document_derivations`

```text
id                  ULID PK
project_id          ULID FK
source_document_id  ULID FK
target_document_id  ULID FK
processor           string
processor_version   integer/string
configuration_json  JSON
auto_regenerate     boolean default true
active              boolean default true
created_by_user_id  ULID nullable
created_at
updated_at
```

Restrições:

- origem e destino pertencem ao mesmo projeto;
- origem != destino;
- target derivado não deve ser reutilizado simultaneamente por derivações incompatíveis sem regra explícita;
- ciclos devem ser impedidos se derivação em cadeia for permitida.

## 12.4. `project_document_derivation_runs`

Recomendado para observabilidade e retries:

```text
id                   ULID PK
derivation_id        ULID FK
source_revision_id   ULID FK
target_revision_id   ULID nullable
status               queued|processing|succeeded|failed
attempt_count        integer
error_code           string nullable
error_message        text nullable
started_at           timestamp nullable
finished_at          timestamp nullable
created_at
updated_at
```

## 12.5. `project_tags`

```text
id             ULID PK
project_id     ULID FK
parent_tag_id  ULID nullable FK -> project_tags
name           string
normalized_name string
created_at
updated_at
```

Restrições:

- `parent_tag_id` deve ser do mesmo projeto;
- ausência de ciclos;
- unicidade entre irmãos por nome normalizado.

## 12.6. `project_document_tag`

Pivot:

```text
document_id ULID FK
tag_id      ULID FK

UNIQUE(document_id, tag_id)
```

Documento e tag precisam pertencer ao mesmo projeto.

## 12.7. Futuro vínculo com tarefas

Não precisa ser implementado nesta fase, mas o modelo deve permitir no futuro:

```text
project_task_documents
task_id
document_id
```

Um mesmo documento poderá ser relacionado a várias tarefas e uma tarefa a vários documentos.

---

# 13. Upload de documento e criação de revisão

## 13.1. Novo documento

Fluxo mínimo:

1. usuário entra em Documentos;
2. escolhe “Adicionar documento”;
3. seleciona arquivo;
4. informa nome;
5. informa revisão inicial;
6. associa tags opcionais;
7. confirma;
8. backend valida permissão;
9. armazena arquivo privado;
10. calcula metadados/checksum;
11. cria documento;
12. cria revisão;
13. define revisão como atual.

## 13.2. Nova revisão de documento existente

Fluxo:

1. abrir documento;
2. “Adicionar revisão”;
3. selecionar novo arquivo;
4. informar novo `revision_label`;
5. upload;
6. validar;
7. registrar revisão;
8. promover como atual;
9. disparar derivações automáticas dependentes.

Não deve ser necessário recriar manualmente os derivados.

## 13.3. Duplicidade

O checksum deve permitir detectar upload binariamente idêntico.

A política exata para duplicidade pode ser definida no planejamento, mas o sistema deve ao menos ser capaz de identificar o caso.

---

# 14. API recomendada

Manter padrão `/api/v1`.

Os endpoints abaixo são uma proposta funcional e podem ser refinados.

## 14.1. Documentos

```text
GET    /api/v1/projects/{projectId}/documents
POST   /api/v1/projects/{projectId}/documents

GET    /api/v1/projects/{projectId}/documents/{documentId}
PUT    /api/v1/projects/{projectId}/documents/{documentId}
DELETE /api/v1/projects/{projectId}/documents/{documentId}
```

Filtros possíveis no GET:

```text
search
tagIds[]
kind
mimeType
status
```

## 14.2. Revisões

```text
GET  /api/v1/projects/{projectId}/documents/{documentId}/revisions
POST /api/v1/projects/{projectId}/documents/{documentId}/revisions

GET  /api/v1/projects/{projectId}/documents/{documentId}/revisions/{revisionId}
GET  /api/v1/projects/{projectId}/documents/{documentId}/revisions/{revisionId}/content
```

O endpoint de conteúdo deve:

- validar acesso;
- suportar Range Requests quando necessário para PDF;
- retornar headers apropriados;
- não expor storage path interno.

## 14.3. Derivações

```text
GET  /api/v1/projects/{projectId}/documents/{documentId}/derivations
POST /api/v1/projects/{projectId}/documents/{documentId}/derivations

GET    /api/v1/projects/{projectId}/derivations/{derivationId}
PUT    /api/v1/projects/{projectId}/derivations/{derivationId}
POST   /api/v1/projects/{projectId}/derivations/{derivationId}/regenerate
DELETE /api/v1/projects/{projectId}/derivations/{derivationId}
```

## 14.4. Tags

```text
GET    /api/v1/projects/{projectId}/tags
POST   /api/v1/projects/{projectId}/tags
PUT    /api/v1/projects/{projectId}/tags/{tagId}
DELETE /api/v1/projects/{projectId}/tags/{tagId}
```

## 14.5. Associação documento-tag

Pode ser feita no próprio update do documento ou por endpoints dedicados.

O contrato deve impedir associação cross-project.

## 14.6. Manifesto offline

Recomenda-se um endpoint específico para preparação/atualização do cache:

```text
GET /api/v1/projects/{projectId}/offline-manifest
```

O manifesto pode retornar:

- versão/snapshot do projeto;
- `generated_at`;
- identificador do usuário;
- documento/revisão atual;
- checksum;
- tamanho;
- URL autorizada para cache;
- metadados necessários para leitura offline.

O formato deve permitir atualização incremental baseada em IDs/checksums.

---

# 15. Armazenamento de arquivos

## 15.1. Laravel Filesystem

Usar a abstração já existente.

Deve funcionar inicialmente em:

```text
local -> storage/app/private
```

e permanecer compatível com:

```text
S3
```

## 15.2. Não depender do path físico

Banco e domínio não devem assumir que o arquivo está necessariamente no filesystem local.

Registrar:

```text
storage_disk
storage_key
```

e usar `Storage::disk(...)`.

## 15.3. Estrutura física sugerida

Exemplo interno:

```text
projects/{projectId}/documents/{documentId}/revisions/{revisionId}/content
```

O nome original fica em metadados, não precisa ser o filename físico.

## 15.4. Arquivos derivados

Arquivo derivado também passa pela mesma infraestrutura de storage e integridade que um upload normal.

---

# 16. Processamento assíncrono e filas

Derivações devem ser processadas por fila.

O request que cria uma derivação não deve precisar manter uma conexão HTTP aberta até o fim do processamento.

Fluxo:

```text
request
  ↓
cria DocumentDerivation
  ↓
cria DerivationRun(status=queued)
  ↓
dispatch Job
  ↓
worker
  ↓
status=processing
  ↓
processador
  ↓
valida arquivo
  ↓
cria revision
  ↓
status=succeeded
```

Em falha:

```text
status=failed
error_code
error_message
```

O frontend deve conseguir acompanhar esse estado.

Retry manual deve ser possível.

Política de retry automático pode ser configurada no planejamento, diferenciando:

- falha temporária;
- PDF inválido;
- configuração inválida;
- arquivo ausente;
- erro interno.

---

# 17. Permissões

Documentos herdam o contexto de autorização do projeto.

## 17.1. Reader

Pode:

- listar;
- pesquisar;
- filtrar;
- visualizar;
- baixar;
- consultar tags;
- consultar histórico;
- consultar documentos derivados.

Não pode:

- upload;
- adicionar revisão;
- renomear;
- alterar tags;
- criar derivação;
- alterar derivação;
- remover documento.

## 17.2. Editor

Pode realizar mutações documentais normais do projeto, sujeito às regras definidas pelo produto.

## 17.3. Owner

Possui capacidades de editor e ações administrativas correspondentes às regras atuais do projeto.

## 17.4. Offline sobrescreve permissão de edição

Mesmo que o usuário seja `owner` ou `editor`, quando a aplicação estiver offline ele se comporta como somente leitura.

Essa restrição não é negociável.

---

# 18. Modo offline somente leitura

Esta seção é requisito central da feature.

## 18.1. Princípio

O modo offline é um **cache de leitura**, não um sistema offline-first.

A aplicação não deve possuir:

- outbox de comandos;
- fila local de mutações;
- reenvio posterior de alterações;
- merge;
- last-write-wins;
- resolução de conflitos;
- edição offline persistente.

Toda mutação requer servidor online.

## 18.2. Dados disponíveis offline

Quando um projeto for preparado para uso offline, o dispositivo deve armazenar uma cópia de leitura suficiente para navegação.

O snapshot deve abranger, no mínimo, os dados necessários para consultar:

- informações do projeto;
- estrutura/seções;
- tarefas;
- dependências/relacionamentos exibidos;
- dados necessários para renderizar a visualização de leitura já suportada;
- índice de documentos;
- tags;
- revisão atual de cada documento;
- metadados dos documentos;
- arquivos binários das revisões atuais destinadas ao modo offline;
- documentos derivados atuais.

O objetivo é que um profissional em campo consiga abrir o projeto e consultar seu conteúdo sem internet.

## 18.3. Revisões offline

Para controlar volume de armazenamento, o baseline deve considerar:

> armazenar offline a **revisão atual** de cada documento e de cada documento derivado.

O histórico completo de revisões não precisa ser pré-cacheado no MVP.

Se posteriormente houver opção de armazenar histórico, isso deve ser tratado como extensão explícita.

## 18.4. Ativação

A interface deve possuir uma ação equivalente a:

```text
Disponibilizar este projeto offline
```

ou:

```text
Baixar para uso offline
```

Antes de declarar o projeto “disponível offline”, o sistema deve completar e verificar o cache necessário.

## 18.5. Conteúdo do cache

Recomendação técnica:

### IndexedDB

Armazenar:

- snapshots JSON;
- índice do projeto;
- tags;
- metadados de documento;
- manifestos;
- versão do snapshot;
- `last_refreshed_at`;
- associação com usuário;
- associação com projeto;
- estado do download offline.

### Cache Storage

Armazenar:

- shell estático;
- assets;
- PDFs;
- imagens;
- outros binários necessários.

Arquivos grandes não devem ser convertidos para Base64 em IndexedDB sem necessidade.

## 18.6. PWA existente

O Ganttist já possui:

```text
public/manifest.webmanifest
public/sw.js
public/offline.html
resources/js/pwa.test.ts
```

O Service Worker atual:

- cacheia principalmente assets estáticos;
- não cacheia `/api/`;
- possui página genérica informando que a aplicação precisa de conexão.

Essa estratégia precisa evoluir.

Não é recomendado simplesmente aplicar “cache-first” a todas as respostas autenticadas da API.

Deve existir uma camada explícita de offline data, associada a:

- usuário;
- projeto;
- versão;
- timestamp.

## 18.7. Sincronização permitida

O termo “sincronização” deve ser interpretado com cuidado.

O sistema pode, **enquanto online**, atualizar o cache local com o estado atual do servidor.

Isso é uma atualização **unidirecional**:

```text
Servidor → dispositivo
```

Nunca:

```text
Dispositivo offline → servidor
```

porque não existem mutações offline.

## 18.8. Atualização do cache

Quando online:

1. aplicação consulta servidor;
2. obtém manifesto/snapshot novo;
3. compara IDs, versões e checksums;
4. baixa o que mudou;
5. mantém o que não mudou;
6. remove do cache revisões atuais substituídas, conforme política;
7. grava `last_refreshed_at`.

## 18.9. Estado “offline ready”

Não considerar o projeto disponível offline apenas porque o usuário clicou no botão.

Só marcar como pronto após:

- snapshot salvo;
- índice salvo;
- binários obrigatórios armazenados;
- checksums/tamanhos verificados quando aplicável.

Se o download for interrompido, mostrar estado incompleto.

## 18.10. Limites de armazenamento

Usar APIs do navegador quando disponíveis:

```text
navigator.storage.estimate()
navigator.storage.persist()
```

A interface deve:

- mostrar tamanho estimado;
- detectar falha de quota;
- informar quando o cache não pôde ser concluído;
- não prometer disponibilidade offline se o navegador não tiver armazenado o conteúdo.

## 18.11. Restrições offline de escrita

Ao detectar ausência de conectividade:

- ocultar ou desabilitar ações de mutação;
- impedir submit de formulários;
- impedir drag/drop que persista alteração;
- impedir criação/edição/exclusão de tarefas;
- impedir alteração de datas;
- impedir alteração de dependências;
- impedir comentários;
- impedir checklists;
- impedir gestão de membros;
- impedir uploads;
- impedir criação de revisão;
- impedir edição de tags;
- impedir criação/alteração de derivação;
- impedir exclusões;
- impedir qualquer outra chamada de mutação.

Não basta depender da falha de rede.

A UI deve conscientemente entrar em modo read-only.

## 18.12. Tentativa de alteração offline

Se o usuário tentar acionar uma ação não permitida, mostrar mensagem explícita, por exemplo:

> “Você está offline. Alterações exigem conexão com o servidor.”

Não usar mensagens genéricas como:

> “Erro desconhecido.”

## 18.13. Perda de conexão durante edição

Se a conexão cair antes do envio de uma mutação:

- não registrar a alteração como pendente;
- não criar item de sincronização posterior;
- informar que a alteração não foi salva;
- manter o estado canônico como o último estado confirmado pelo servidor.

Uma UI pode manter temporariamente texto não salvo apenas na memória da tela, mas isso não pode virar uma operação persistente offline a ser sincronizada depois.

## 18.14. Indicador offline

Enquanto estiver sem conexão e exibindo cache, mostrar um indicador persistente e claro.

Exemplo:

```text
OFFLINE — SOMENTE LEITURA
Dados armazenados em: 19/09/2026 10:30
As informações podem estar desatualizadas.
```

A mensagem deve continuar visível enquanto o usuário navega em dados offline.

## 18.15. Dados potencialmente desatualizados

Sempre que conteúdo vier do cache porque o servidor não pôde ser consultado, a aplicação deve comunicar:

- que está offline;
- que o conteúdo é uma cópia local;
- quando ocorreu a última atualização bem-sucedida;
- que o servidor pode possuir dados mais recentes.

## 18.16. Inicialização sem internet

Cenários:

### Existe cache válido

Abrir o shell e oferecer os projetos que estão disponíveis offline.

### Não existe cache

Mostrar estado offline sem inventar dados e explicar que o projeto precisa ter sido disponibilizado offline anteriormente.

## 18.17. Retorno da conexão

Quando a conexão voltar:

- mostrar que a aplicação voltou a ficar online;
- atualizar o servidor normalmente;
- oferecer/realizar atualização do cache;
- não enviar nenhuma mutação offline, porque nenhuma deveria existir.

## 18.18. Segurança do cache

Dados offline são cópias privadas do projeto no dispositivo.

Deve existir:

```text
Remover dados offline deste projeto
```

e uma ação global de limpeza de dados offline.

Ao fazer logout, os dados privados offline relacionados à sessão/usuário devem ser removidos ou tornados inacessíveis de forma segura, de acordo com a política definida no planejamento.

Nunca misturar caches entre usuários diferentes do mesmo navegador.

## 18.19. Revogação de acesso

Limitação inerente:

se um usuário perder acesso ao projeto enquanto o dispositivo estiver offline, o servidor não consegue comunicar essa revogação imediatamente.

Na próxima conexão:

- revalidar acesso;
- se acesso foi removido, apagar/inutilizar o cache daquele projeto.

A interface/documentação deve reconhecer essa característica.

---

# 19. Service Worker: comportamento esperado

O Service Worker deve evoluir sem transformar a aplicação em offline-first.

## 19.1. Shell

Pode continuar utilizando estratégia adequada para:

- JS;
- CSS;
- ícones;
- fontes;
- HTML shell.

## 19.2. Dados autenticados

Não usar cache transparente indiscriminado de toda API autenticada.

Dados de projeto devem ser persistidos deliberadamente pelo módulo offline.

## 19.3. Mutações

Requests:

```text
POST
PUT
PATCH
DELETE
```

não devem ser enfileirados pelo Service Worker.

Não implementar Workbox Background Sync para mutações.

Não implementar replay automático.

## 19.4. Downloads offline

Arquivos de revisões atuais podem ser pré-carregados em Cache Storage por uma rotina explícita.

A chave de cache deve ser associável de forma segura a:

- usuário;
- projeto;
- documento;
- revisão.

## 19.5. Limpeza de versão

Mudanças de versão do frontend não devem apagar indiscriminadamente gigabytes de documentos offline sem migração/política explícita.

Separar:

- cache de assets da aplicação;
- cache de dados/documentos offline.

---

# 20. UX para documentos PDF

## 20.1. Viewer

Usar PDF.js.

Recursos mínimos:

- paginação;
- zoom;
- ajustar à largura;
- ajuste à página;
- indicador de página;
- modo criar derivado.

## 20.2. Seleção de recorte

Overlay independente do canvas de renderização.

A seleção deve ser traduzida para coordenadas PDF normalizadas.

Zoom não deve alterar a geometria persistida.

## 20.3. Múltiplas regiões

O usuário deve poder criar diversas áreas em uma mesma prancha antes de confirmar.

Cada região representa uma derivação/documento separado.

Exemplo:

```text
Área 1 → "Pavimento Térreo"
Área 2 → "Pavimento Superior"
Área 3 → "Cobertura"
Área 4 → "Caixa d'água"
```

Cada item recebe:

- nome;
- tags;
- auto-regenerate.

## 20.4. Feedback de processamento

Depois de salvar:

```text
Pavimento Térreo — Processando...
```

e depois:

```text
Pavimento Térreo — Disponível
```

ou:

```text
Pavimento Térreo — Falha na geração
[Tentar novamente]
```

---

# 21. Estados e consistência

## 21.1. Upload

Uma revisão só deve se tornar atual após upload e validação completos.

## 21.2. Derivação

Uma revisão derivada só deve se tornar atual após:

- geração bem-sucedida;
- arquivo presente;
- validação básica do formato;
- checksum calculado;
- registro persistido.

## 21.3. Transações

Metadados relacionais devem usar transações curtas.

Processamento de arquivo pesado não deve manter transação de banco aberta durante a execução do Python.

Fluxo recomendado:

1. transação cria run/estado;
2. commit;
3. processamento externo;
4. nova transação registra sucesso/resultado.

## 21.4. Idempotência

Jobs de derivação devem ser idempotentes ou protegidos contra duplicação.

A mesma combinação:

```text
derivation_id + source_revision_id
```

não deve gerar várias revisões atuais equivalentes por retry acidental.

Usar constraint/lock lógico apropriado.

---

# 22. Exclusão e histórico

O planejamento deve preferir preservação de rastreabilidade.

Recomendação:

- documentos podem ser arquivados/soft-deleted;
- revisões prontas não devem ser silenciosamente sobrescritas;
- uma derivação excluída/inativada não deve apagar automaticamente revisões históricas já geradas;
- remoção física de binários deve possuir política deliberada.

Se for implementada exclusão permanente, dependências e proveniência devem ser tratadas explicitamente.

---

# 23. Auditoria e observabilidade

Registrar eventos relevantes, ao menos em logs/estrutura já existente:

- criação de documento;
- upload de revisão;
- promoção de revisão;
- criação de derivação;
- alteração da configuração de derivação;
- execução de derivação;
- falha;
- retry;
- sucesso;
- ativação/desativação de auto-regenerate;
- cache offline preparado;
- cache offline removido.

Para jobs:

- `derivation_id`;
- `source_revision_id`;
- `target_document_id`;
- duração;
- processor/version;
- status;
- erro sanitizado.

Não registrar conteúdo sensível dos arquivos.

---

# 24. Performance

## 24.1. Arquivos grandes

Uploads/downloads precisam suportar arquivos grandes sem carregar o binário inteiro em memória quando não necessário.

## 24.2. PDF

O endpoint deve considerar HTTP Range para PDF.js e navegação eficiente em PDFs grandes.

## 24.3. Biblioteca

Listagem deve ser paginável.

Busca e filtro de tags devem possuir índices adequados.

## 24.4. Derivação

Processamento pesado fora do request web.

## 24.5. Cache offline

Downloads devem indicar progresso e permitir retomada futura se isso puder ser implementado de forma confiável; não é obrigatório na primeira entrega, mas o fluxo deve lidar com interrupção sem declarar sucesso falso.

---

# 25. Validações de autorização

Nenhum endpoint documental pode confiar apenas no `projectId` vindo do frontend.

Sempre validar:

```text
user
  ↓
membership/role
  ↓
project
  ↓
document
  ↓
revision/derivation/tag
```

IDs pertencentes a projetos diferentes devem ser rejeitados.

Exemplo de ataque a impedir:

```text
POST /projects/A/documents/X/tags
tag_id = tag_do_projeto_B
```

---

# 26. Estratégia de testes

## 26.1. Backend unit/domain tests

Cobrir:

- criação de tags;
- hierarquia;
- prevenção de ciclos;
- associação documento-tag;
- criação de revisão;
- promoção de revisão atual;
- proveniência;
- regras de derivação;
- idempotência;
- auto-regenerate;
- permissões.

## 26.2. Integration tests

Cobrir:

- upload;
- download autorizado;
- Range Requests;
- job de derivação;
- falha do processador;
- retry;
- nova revisão disparando derivado;
- storage local;
- contrato compatível com storage alternativo.

## 26.3. PDF processor tests

Usar fixtures contendo:

- vetores;
- texto;
- imagem raster dentro de PDF;
- página paisagem;
- rotação;
- múltiplos tamanhos;
- CropBox diferente de MediaBox.

Validar:

- dimensões da saída;
- região correta;
- PDF abre;
- texto/vetor não virou screenshot integral;
- configuração reaplicada em nova revisão.

## 26.4. Frontend tests

Cobrir:

- biblioteca;
- seleção de tags;
- árvore de tags;
- viewer;
- transformação coordenada tela → PDF;
- múltiplos recortes;
- estados de job;
- read-only por role;
- read-only offline.

## 26.5. PWA/offline tests

Cenários obrigatórios:

1. projeto não preparado + offline;
2. projeto preparado + offline;
3. abrir tarefa em cache;
4. abrir PDF em cache;
5. abrir documento derivado em cache;
6. tentar editar tarefa offline;
7. tentar mover tarefa offline;
8. tentar criar dependência offline;
9. tentar upload offline;
10. tentar criar tag offline;
11. tentar criar derivação offline;
12. perder conexão enquanto formulário está aberto;
13. reconectar;
14. atualizar cache;
15. nova revisão substitui arquivo atual no cache;
16. quota insuficiente;
17. logout/limpeza de cache;
18. troca de usuário no mesmo navegador;
19. acesso ao projeto revogado e posteriormente reconectado.

Critério fundamental:

> nenhum teste offline pode resultar na criação de uma mutação pendente para replay posterior.

---

# 27. Critérios de aceite funcionais

## AC-001 — Biblioteca de documentos

Dado um projeto com acesso autorizado, o usuário consegue abrir a área Documentos e visualizar os documentos pertencentes ao projeto.

## AC-002 — Documento genérico

O sistema aceita e registra arquivos que não sejam apenas pranchas/PDFs.

## AC-003 — Revisões

Ao adicionar uma nova revisão a um documento, a revisão anterior permanece no histórico e a nova passa a ser a atual após sucesso.

## AC-004 — Tags múltiplas

Um documento pode possuir múltiplas tags.

## AC-005 — Tags hierárquicas

Uma tag pode possuir uma tag-pai e a estrutura pode crescer sem número fixo de níveis.

## AC-006 — Sem pastas funcionais

A organização de documentos não exige colocação em diretórios/pastas de usuário.

## AC-007 — Recorte de PDF

Usuário autorizado seleciona visualmente uma região de um PDF e cria um novo documento derivado.

## AC-008 — Preservação de qualidade

O documento derivado de PDF não é uma captura de tela e mantém conteúdo vetorial/textual quando presente na origem.

## AC-009 — Múltiplos recortes

Uma mesma sessão de edição pode produzir múltiplos documentos derivados independentes.

## AC-010 — Configuração persistida

A geometria e parâmetros usados na derivação permanecem armazenados.

## AC-011 — Nova revisão da origem

Quando a origem recebe nova revisão, derivações com auto-regenerate criam novas revisões derivadas.

## AC-012 — Proveniência

É possível determinar qual revisão de origem produziu cada revisão derivada.

## AC-013 — Falha segura

Falha ao gerar a nova revisão derivada não invalida a última revisão derivada pronta.

## AC-014 — PWA offline

Projeto previamente preparado pode ser aberto sem conexão.

## AC-015 — Documentos offline

Revisões atuais armazenadas previamente podem ser abertas sem conexão.

## AC-016 — Indicação de offline

A interface exibe claramente que está em modo offline, somente leitura, com dados potencialmente desatualizados.

## AC-017 — Bloqueio de tarefas offline

Nenhuma tarefa pode ser criada, editada, movida, concluída, reaberta ou excluída offline.

## AC-018 — Bloqueio de relacionamentos offline

Nenhuma dependência ou outro relacionamento persistente pode ser alterado offline.

## AC-019 — Bloqueio documental offline

Nenhum documento, revisão, tag ou derivação pode ser alterado offline.

## AC-020 — Sem fila offline

Alteração tentada offline nunca é registrada para envio futuro.

## AC-021 — Reconexão sem conflitos

Ao voltar online, o cliente apenas atualiza o estado/cache a partir do servidor; não existe upload de alterações locais offline.

## AC-022 — Cache desatualizado

Ao usar cache, a interface informa o horário da última atualização bem-sucedida.

---

# 28. Fluxos principais

## 28.1. Criar documento

```text
Projeto
→ Documentos
→ Adicionar documento
→ arquivo
→ nome
→ revisão
→ tags
→ salvar
```

## 28.2. Criar derivado de PDF

```text
Documentos
→ abrir PDF
→ Criar derivado
→ Recortar região
→ selecionar área
→ nome
→ tags
→ regenerar automaticamente = sim
→ salvar
→ processamento em fila
→ documento derivado disponível
```

## 28.3. Nova revisão + regeneração

```text
Documento origem R03
→ Adicionar revisão R04
→ upload concluído
→ R04 atual
→ localizar derivações
→ enfileirar processors
→ gerar derivados R04
→ promover derivados
```

## 28.4. Preparar offline

```text
Projeto online
→ Disponibilizar offline
→ calcular manifesto
→ verificar storage
→ cachear snapshot
→ cachear documentos atuais
→ validar
→ marcar "Disponível offline"
```

## 28.5. Uso em campo

```text
Sem internet
→ abrir PWA
→ abrir projeto em cache
→ banner OFFLINE / SOMENTE LEITURA
→ navegar tarefas
→ navegar tags
→ abrir PDF
→ abrir derivado
→ tentativa de editar
→ bloqueio + mensagem "Alterações exigem conexão"
```

---

# 29. Arquitetura alvo resumida

```text
┌────────────────────────────────────────────────────────────┐
│                       Vue 3 / PWA                          │
│                                                            │
│ Project Workspace                                          │
│ ├── Tarefas                                                │
│ ├── Gantt                                                  │
│ └── Documentos                                             │
│     ├── Document Library                                   │
│     ├── Tags                                               │
│     ├── PDF.js Viewer                                      │
│     ├── Crop Overlay                                       │
│     └── Offline Cache Manager                              │
└───────────────────────────┬────────────────────────────────┘
                            │ HTTP JSON / file streaming
                            ▼
┌────────────────────────────────────────────────────────────┐
│                         Laravel                            │
│                                                            │
│ Auth / Project Authorization                               │
│ Documents Domain                                           │
│ ├── Documents                                              │
│ ├── Revisions                                              │
│ ├── Tags                                                   │
│ ├── Derivations                                            │
│ └── Derivation Runs                                        │
│                                                            │
│ Jobs / Queue ───────────────────────────┐                  │
└───────────────┬─────────────────────────┼──────────────────┘
                │                         │
                ▼                         ▼
          MySQL metadata           Python processor
                                    PyMuPDF
                                         │
                                         ▼
                                  Derived PDF
                │
                ▼
        Laravel Filesystem
        ├── private local
        └── S3 compatible
```

Offline:

```text
SERVER ONLINE
    │
    │ one-way refresh
    ▼
PWA DEVICE
├── IndexedDB: snapshots/metadados
└── Cache Storage: arquivos atuais

SEM INTERNET
PWA DEVICE
└── leitura apenas

Não existe:
PWA offline changes → queue → server
```

---

# 30. Recomendações para o planejamento de desenvolvimento

Antes de codificar, o agente implementador deve produzir um plano contendo:

1. migrations;
2. entidades/serviços;
3. regras de autorização;
4. contratos de API;
5. estratégia de storage;
6. estratégia de upload de arquivos grandes;
7. integração Python/PyMuPDF;
8. contrato do processor `pdf.crop.region`;
9. sistema de coordenadas PDF;
10. jobs e estados;
11. estratégia de idempotência;
12. componentes Vue;
13. fluxo UX da biblioteca;
14. fluxo UX do PDF crop;
15. árvore de tags;
16. estratégia explícita de offline cache;
17. migração do Service Worker atual;
18. estratégia de segurança do cache;
19. testes;
20. rollout.

O plano deve manter a feature isolada modularmente e evitar concentrar toda a implementação em `App.vue` ou `ProjectController.php`.

---

# 31. Decisões fechadas que não devem ser reinterpretadas

Estas decisões fazem parte do requisito:

1. **Documentos pertencem a Projetos existentes do Ganttist.**
2. **Não criar um sistema independente para documentos.**
3. **Documento é genérico, não é sinônimo de prancha.**
4. **Organização funcional é por tags, não por pastas.**
5. **Tags são hierárquicas e de profundidade arbitrária.**
6. **Um documento pode possuir múltiplas tags.**
7. **Documentos possuem revisões históricas.**
8. **Nova revisão não apaga a anterior.**
9. **Documento derivado é um documento completo.**
10. **Derivação é uma regra persistida.**
11. **Primeiro processor é recorte de região de PDF.**
12. **O recorte de PDF deve preservar qualidade vetorial; screenshot não é aceitável.**
13. **A regra de recorte deve sobreviver à troca de revisão.**
14. **Nova revisão da origem deve poder regenerar automaticamente derivados.**
15. **Auto-regenerate deve ser padrão para recortes criados pelo usuário.**
16. **Cada revisão derivada deve apontar para a revisão exata de origem.**
17. **Processamento deve ser assíncrono.**
18. **Reutilizar Laravel/Vue/MySQL/storage/queue do Ganttist.**
19. **Python/PyMuPDF deve atuar como processador especializado, não como um segundo produto web.**
20. **A aplicação continua PWA/web, sem obrigação de aplicativo nativo.**
21. **Modo offline é estritamente somente leitura.**
22. **Nenhuma mutação pode ser feita offline.**
23. **Não existe fila de alterações offline.**
24. **Não existe posterior sincronização de alterações feitas offline.**
25. **Não existe resolução de conflitos offline, pois não existem alterações offline.**
26. **O cache é atualizado apenas do servidor para o dispositivo quando há conexão.**
27. **A interface deve indicar claramente modo offline e possível desatualização.**
28. **Tentativas de alteração offline devem ser bloqueadas com mensagem específica.**
29. **Documentos atuais preparados para offline precisam abrir sem conexão.**
30. **O estado de cache deve ser associado ao usuário e ao projeto, sem vazamento entre sessões.**

---

# 32. Pontos que o agente pode decidir no planejamento

O implementador pode propor a melhor solução para:

- layout visual exato da biblioteca;
- paginação;
- tabela vs. cards;
- nomes finais de classes PHP;
- nomes finais das tabelas desde que mantenham o domínio;
- biblioteca auxiliar para IndexedDB;
- política de retry automático;
- tamanho máximo de upload;
- estratégia de upload multipart;
- estratégia de URLs temporárias no S3;
- uso de soft delete;
- política de retenção física de revisões excluídas;
- algoritmo exato para cálculo de espaço offline;
- suporte opcional futuro ao cache de revisões históricas;
- thumbnails/previews;
- progresso de download;
- retomada de download interrompido;
- comportamento visual detalhado ao recuperar conexão.

Essas decisões não podem violar as decisões fechadas da seção anterior.

---

# 33. Resultado esperado

Ao final da implementação, o Ganttist deve ter evoluído de um sistema de gerenciamento de projetos/tarefas para um sistema que também mantém uma **biblioteca documental versionada por projeto**, capaz de gerar automaticamente novos documentos a partir de outros documentos e de disponibilizar o conteúdo do projeto para consulta em campo sem internet.

O primeiro caso concreto será o uso de grandes pranchas PDF:

```text
Prancha original PDF
      ↓
seleção visual
      ↓
Documento derivado: Pavimento Térreo
Documento derivado: Pavimento Superior
Documento derivado: Cobertura
Documento derivado: Detalhe de esquadrias
```

Quando a prancha original mudar de revisão:

```text
Origem R04
      ↓
reaplicar regras persistidas
      ↓
Derivados R04
```

O profissional poderá preparar o projeto antes de ir ao campo e consultar essas informações offline.

Enquanto estiver offline:

```text
Consultar = permitido
Alterar = proibido
Criar = proibido
Excluir = proibido
Sincronizar mutações = inexistente
```

Essa restrição é deliberada para manter o modelo consistente, simples e livre de conflitos de sincronização.
