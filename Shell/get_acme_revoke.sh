#!/bin/bash
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:$PATH"

DOMAIN=$1
ACMEPATH=$2

if [ -z "$DOMAIN" ]; then
    echo "Uso: $0 <dominio> <caminho_acme>"
    exit 1
fi

# Gera apenas o desafio DNS manual, sem emitir o certificado
ORDER=$($ACMEPATH/acme.sh --revoke --domain "$DOMAIN"  --ecc)


echo $ORDER
