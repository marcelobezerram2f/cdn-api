#!/bin/bash
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:$PATH"

ACMEPATH=$1

# Gera apenas o desafio DNS manual, sem emitir o certificado
ORDER=$($ACMEPATH/acme.sh --install --home  $ACMEPATH )


echo $ORDER
