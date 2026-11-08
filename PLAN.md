# Roadmap & Plano de Arquitetura - EDPay OpenCart Extension

## Fases de Desenvolvimento

### Fase 1: Arquitetura & Configuração Admin (Concluído)
- [x] Estrutura MVC-L do OpenCart (`admin/controller`, `admin/view`, `admin/model`, `catalog/controller`, `catalog/model`).
- [x] Ecrã de configuração de chaves API (Sandbox e Produção) no backoffice.

### Fase 2: Checkout & Callback Controller (Em Desenvolvimento)
- [ ] Catalog controller para geração de sessão no `/v1/checkout/sessions`.
- [ ] Callback endpoint para IPN webhook (`index.php?route=extension/payment/edpay/callback`).

### Fase 3: Empacotamento OCMOD
- [ ] Gerador de ficheiro `.ocmod.zip` compatível com o instalador do OpenCart 3.x e 4.x.
- [ ] Publicação no OpenCart Marketplace.
