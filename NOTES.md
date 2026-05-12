# Notas Técnicas — Exportação Massiva de Alunos

## Como Usar a Exportação

1. Acesse o painel Filament em `http://localhost/admin`
2. Faça login com `admin@admin.com` / `admin`
3. Na listagem de **Alunos**, clique no botão **"Exportar Alunos"** (ícone verde no cabeçalho)
4. Confirme no modal de confirmação clicando em **"Gerar planilha"**
5. A exportação será processada em segundo plano — você receberá uma **notificação no sininho** (🔔) assim que o arquivo estiver pronto
6. Clique em **"Baixar Planilha"** na notificação para fazer o download do Excel

> **Nota:** O processamento de ~200 mil registros leva aproximadamente 60–70 segundos. Durante esse período, o sistema permanece 100% responsivo.

---

## Decisões Técnicas

### Performance

O maior desafio do projeto é exportar centenas de milhares de registros sem estourar a memória do servidor e sem travar a interface do usuário. Para resolver isso, tomei as seguintes decisões:

- **Processamento assíncrono via Queue + Redis:** O Job `ExportAlunosJob` é despachado para uma fila Redis e processado por um worker dedicado em background. Isso libera o servidor web imediatamente após o clique do usuário, mantendo o painel 100% responsivo durante toda a exportação.

- **Query Builder (`DB::table`) no lugar de Eloquent:** Para a exportação massiva, substituí o Eloquent ORM pelo Query Builder puro. Isso elimina a hidratação de Models (que consome memória significativa por registro), reduzindo drasticamente o consumo de RAM.

- **`whereExists` para filtrar alunos com matrícula direto no SQL:** Em vez de carregar todos os usuários e filtrar no PHP com `continue`, utilizo uma subquery `WHERE EXISTS` que faz o banco de dados retornar apenas os registros relevantes. Isso reduz o tráfego de dados entre o MySQL e o PHP.

- **Chunking de 2.000 registros:** Os dados são processados em lotes de 2.000 registros por iteração. A cada chunk, as informações complementares (documentos, endereços, matrículas) são carregadas em batch via `whereIn`, evitando o problema N+1.

- **Streaming com OpenSpout:** Cada linha é escrita no arquivo `.xlsx` imediatamente após o processamento, sem acumular o resultado final em memória. Isso mantém o consumo de RAM estável (~66MB) independentemente do volume de dados.

- **Ordenação no SQL:** A ordenação das matrículas por `data_de_criacao DESC` é feita diretamente na query, eliminando a necessidade de `usort()` em PHP (que exigiria carregar todos os dados em memória).

### Qualidade do Código

A arquitetura segue os princípios de **Responsabilidade Única (SRP)** e **separação de camadas**:

- **`ExportAlunosAction`** → Camada de interface (Filament). Responsável pelo botão, modal de confirmação, feedback visual e trava anti-spam via Cache.
- **`ExportAlunosJob`** → Camada de orquestração. Gerencia o ciclo de vida assíncrono (dispatch, retries, notificações de sucesso/falha).
- **`AlunoExportService`** → Camada de domínio. Contém toda a lógica de negócio da exportação (queries, chunking, escrita do Excel).
- **`DocumentFormatter`** → Utilitário de formatação. Aplica máscaras em CPF, RG e CEP via Regex, isolando essa responsabilidade.

### UX (Experiência do Usuário)

- **Modal de confirmação** antes de iniciar a exportação, informando o usuário que o processo será em segundo plano.
- **Notificação em tempo real** via Database Notifications do Filament (polling automático no sininho 🔔), com botão de download direto na notificação.
- **Trava anti-spam:** Um Cache Lock impede que o mesmo usuário dispare múltiplas exportações simultâneas. Se ele clicar novamente, recebe um aviso amigável pedindo para aguardar.
- **Tratamento de falhas:** Se o Job falhar após 3 tentativas (com backoff progressivo de 1min, 2min e 5min), o usuário recebe uma notificação de erro e a trava é automaticamente liberada.
- **`ShouldBeUnique`:** O Job implementa a interface `ShouldBeUnique` com `uniqueId` baseado no ID do usuário, garantindo que o Redis rejeite jobs duplicados mesmo em cenários de concorrência.

### Infraestrutura (Docker)

- **Worker isolado:** O `docker-compose.yml` possui um serviço `queue` dedicado exclusivamente ao processamento de filas, separado do servidor web (`app`). Isso garante que a exportação pesada não impacte a performance do painel.
- **Healthchecks:** MySQL e Redis possuem healthchecks configurados. Os serviços `app` e `queue` só iniciam após a confirmação de que as dependências estão saudáveis (`service_healthy`).
- **Redis centralizado:** Cache, sessões e filas utilizam Redis, eliminando a dependência de tabelas de banco de dados para gerenciamento de estado temporário.

---

## Arquivos Criados/Modificados

| Arquivo | Tipo | Descrição |
|---|---|---|
| `app/Filament/Actions/ExportAlunosAction.php` | **Novo** | Action dedicada com modal, trava anti-spam e dispatch do Job |
| `app/Jobs/ExportAlunosJob.php` | **Modificado** | Refatorado com ShouldBeUnique, retries, backoff e tratamento de falhas |
| `app/Services/Export/AlunoExportService.php` | **Novo** | Service de domínio com chunking, Query Builder e streaming OpenSpout |
| `app/Support/Formatters/DocumentFormatter.php` | **Novo** | Formatação de CPF, RG e CEP com máscaras via Regex |
| `app/Filament/Resources/UserResource.php` | **Modificado** | Adicionado `modelLabel`, `deferLoading` e filtro `has('matriculas')` |
| `app/Filament/Resources/UserResource/Pages/ListUsers.php` | **Modificado** | Integração da ExportAlunosAction no cabeçalho |
| `docker-compose.yml` | **Modificado** | Adicionado serviço `queue`, healthchecks e limites de recursos |
| `.env` / `.env.example` | **Modificado** | Configuração de Redis para cache, sessão e fila |
| `config/app.php` | **Modificado** | Locale padrão alterado para `pt_BR` |
