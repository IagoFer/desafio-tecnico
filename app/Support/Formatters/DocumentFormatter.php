<?php

namespace App\Support\Formatters;

class DocumentFormatter
{
    /**
     * Formata uma string de 11 dígitos para o padrão CPF (000.000.000-00).
     */
    public static function formatCpf(?string $cpf): string
    {
        $cpf = preg_replace('/\D/', '', $cpf ?? '');
        
        if (preg_match('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', $cpf, $matches)) {
            return "{$matches[1]}.{$matches[2]}.{$matches[3]}-{$matches[4]}";
        }
        
        return $cpf;
    }

    /**
     * Formata uma string para o padrão RG (00.000.000-0).
     */
    public static function formatRg(?string $rg): string
    {
        $rg = preg_replace('/\D/', '', $rg ?? '');
        
        if (preg_match('/^(\d{2})(\d{3})(\d{3})(\d{1})$/', $rg, $matches)) {
            return "{$matches[1]}.{$matches[2]}.{$matches[3]}-{$matches[4]}";
        } elseif (strlen($rg) > 0) {
            return substr($rg, 0, -1) . '-' . substr($rg, -1);
        }
        
        return $rg;
    }

    /**
     * Formata uma string de 8 dígitos para o padrão CEP (00000-000).
     */
    public static function formatCep(?string $cep): string
    {
        $cep = preg_replace('/\D/', '', $cep ?? '');
        
        if (preg_match('/^(\d{5})(\d{3})$/', $cep, $matches)) {
            return "{$matches[1]}-{$matches[2]}";
        }
        
        return $cep;
    }
}
