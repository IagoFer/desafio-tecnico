<?php

namespace Tests\Unit;

use App\Support\Formatters\DocumentFormatter;
use PHPUnit\Framework\TestCase;

class DocumentFormatterTest extends TestCase
{
    // ==========================================
    // TESTES DE CPF
    // ==========================================

    public function test_formats_valid_cpf_with_only_numbers(): void
    {
        $cpf = '12345678901';
        $formatted = DocumentFormatter::formatCpf($cpf);
        
        $this->assertEquals('123.456.789-01', $formatted);
    }

    public function test_keeps_already_formatted_cpf_intact(): void
    {
        $cpf = '123.456.789-01';
        $formatted = DocumentFormatter::formatCpf($cpf);
        
        $this->assertEquals('123.456.789-01', $formatted);
    }

    public function test_fixes_partially_formatted_cpf(): void
    {
        $cpf = '123456.789-01';
        $formatted = DocumentFormatter::formatCpf($cpf);
        
        $this->assertEquals('123.456.789-01', $formatted);
    }

    public function test_handles_null_cpf_gracefully(): void
    {
        $formatted = DocumentFormatter::formatCpf(null);
        
        $this->assertEquals('', $formatted);
    }

    public function test_handles_incomplete_cpf_gracefully(): void
    {
        // Menos que 11 dígitos, retorna sem formatar padrão (apenas limpo)
        $cpf = '123456';
        $formatted = DocumentFormatter::formatCpf($cpf);
        
        $this->assertEquals('123456', $formatted);
    }

    // ==========================================
    // TESTES DE RG
    // ==========================================

    public function test_formats_valid_rg_with_nine_digits(): void
    {
        $rg = '123456789';
        $formatted = DocumentFormatter::formatRg($rg);
        
        $this->assertEquals('12.345.678-9', $formatted);
    }

    public function test_handles_null_rg_gracefully(): void
    {
        $formatted = DocumentFormatter::formatRg(null);
        
        $this->assertEquals('', $formatted);
    }

    public function test_formats_rg_with_custom_fallback_for_weird_lengths(): void
    {
        // Se o RG não tiver 9 dígitos, ele aplica o fallback "X-Y" nos últimos
        $rg = '123456';
        $formatted = DocumentFormatter::formatRg($rg);
        
        $this->assertEquals('12345-6', $formatted);
    }

    public function test_strips_letters_from_rg_but_keeps_fallback(): void
    {
        $rg = 'A12B34'; // Fica "1234"
        $formatted = DocumentFormatter::formatRg($rg);
        
        $this->assertEquals('123-4', $formatted);
    }

    // ==========================================
    // TESTES DE CEP
    // ==========================================

    public function test_formats_valid_cep_with_only_numbers(): void
    {
        $cep = '12345678';
        $formatted = DocumentFormatter::formatCep($cep);
        
        $this->assertEquals('12345-678', $formatted);
    }

    public function test_keeps_already_formatted_cep_intact(): void
    {
        $cep = '12345-678';
        $formatted = DocumentFormatter::formatCep($cep);
        
        $this->assertEquals('12345-678', $formatted);
    }

    public function test_handles_null_cep_gracefully(): void
    {
        $formatted = DocumentFormatter::formatCep(null);
        
        $this->assertEquals('', $formatted);
    }

    public function test_handles_incomplete_cep_gracefully(): void
    {
        $cep = '12345';
        $formatted = DocumentFormatter::formatCep($cep);
        
        $this->assertEquals('12345', $formatted);
    }
}
