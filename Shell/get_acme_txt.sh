#!/bin/bash
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:$PATH"

DOMAIN=$1
ACMEPATH=$2

if [ -z "$DOMAIN" ]; then
    echo "Uso: $0 <dominio> <caminho_acme>"
    exit 1
fi

# Gera apenas o desafio DNS manual, sem emitir o certificado
ORDER=$($ACMEPATH/acme.sh --issue --dns --days 90 -d "$DOMAIN" --yes-I-know-dns-manual-mode-enough-go-ahead-please --server letsencrypt --ecc --force --debug 2)


# Extrai o valor do TXT
TXT_VALUE=$(echo "$ORDER" | grep "TXT value" | awk -F"'" '{print $2}')
SUBDOMAIN=$(echo "$ORDER" | grep "Domain:" | awk -F": " '{print $2}')

# Valida se o TXT foi gerado corretamente
if [ -n "$TXT_VALUE" ] && [ -n "$SUBDOMAIN" ]; then
    echo "Subdomain: $SUBDOMAIN"
    echo "TXT value: $TXT_VALUE"
else
    echo $ORDER
    exit 1
fi
