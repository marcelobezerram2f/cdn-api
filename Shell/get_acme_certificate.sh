#!/bin/bash

DOMAIN=$1
ACMEPATH=$2

if [ -z "$DOMAIN" ]; then
    echo "Uso: $0 <dominio>"
    exit 1
fi

ORDER=$($ACMEPATH/acme.sh --renew -d "$DOMAIN" --yes-I-know-dns-manual-mode-enough-go-ahead-please --ecc --force)

echo "$ORDER"
