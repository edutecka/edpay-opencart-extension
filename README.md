# Extensão de Pagamento EDPay para OpenCart (3.x & 4.x)

[![Licença: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Versão OpenCart](https://img.shields.io/badge/OpenCart-3.x%20|%204.x-orange.svg)](https://opencart.com)
[![Multi-Gateway](https://img.shields.io/badge/EDPay-Multi--Gateway-0051FA.svg)](https://edpay.co.mz)

Extensão oficial para integrar a plataforma de pagamentos multi-gateway **EDPay** em lojas virtuais **OpenCart**, oferecendo cobranças em Meticais (MZN) via **M-Pesa**, **e-Mola**, **Cartões Visa / Mastercard** e **Referências Bancárias**.

---

## 🛠️ Instalação

1. Extraia o conteúdo deste repositório para o diretório raiz da sua loja OpenCart:
   ```bash
   cp -r admin/ /var/www/opencart/admin/
   cp -r catalog/ /var/www/opencart/catalog/
   ```
2. No painel de administração do OpenCart, aceda a **Extensions > Extensions > Payments**.
3. Localize **EDPay Payment Gateway** e clique no botão verde **Install**.
4. Clique no botão azul **Edit** para configurar as chaves de API.

---

## ⚙️ Configuração

- **Status**: Ativado
- **Public Key**: `pk_live_...` (ou `pk_test_...`)
- **Private Key**: `sk_live_...`
- **Webhook Secret**: `whsec_...`
- **Order Status (Sucesso)**: `Processing`

### URL do Webhook a registar no Painel EDPay:
```
https://sua-loja.co.mz/index.php?route=extension/payment/edpay/webhook
```

---

## 📄 Licença

MIT License. Consulte `LICENSE` para mais detalhes.
