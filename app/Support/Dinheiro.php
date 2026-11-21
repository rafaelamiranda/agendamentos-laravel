<?php

namespace App\Support;

/**
 * Conversão entre o valor digitado ou exibido ("1.234,56") e centavos.
 * Fica só na borda (formulários do painel); o domínio trabalha em inteiros.
 */
final class Dinheiro
{
    /**
     * "120", "120,5", "1.234,56" ou "120.50" para centavos. Devolve null se
     * o texto não for um valor válido. A conversão é feita sobre os dígitos,
     * sem passar por float.
     */
    public static function paraCentavos(string $texto): ?int
    {
        $texto = trim(str_replace(['R$', ' '], '', $texto));

        $padraoBrasileiro = '/^\d{1,3}(\.\d{3})+(,\d{1,2})?$|^\d+(,\d{1,2})?$/';
        $padraoComPonto = '/^\d+\.\d{1,2}$/';

        if (preg_match($padraoBrasileiro, $texto)) {
            [$reais, $centavos] = array_pad(explode(',', str_replace('.', '', $texto)), 2, '0');
        } elseif (preg_match($padraoComPonto, $texto)) {
            [$reais, $centavos] = explode('.', $texto);
        } else {
            return null;
        }

        return (int) $reais * 100 + (int) str_pad($centavos, 2, '0');
    }

    /** 12050 para "120,50". */
    public static function formatar(int $centavos): string
    {
        return number_format($centavos / 100, 2, ',', '.');
    }
}
