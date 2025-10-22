<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ValidIBANValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidIBAN) {
            throw new UnexpectedTypeException($constraint, ValidIBAN::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // Supprime les espaces
        $iban = str_replace(' ', '', strtoupper($value));

        // Vérifie le format de base
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Vérifie la longueur selon le pays
        $countryCode = substr($iban, 0, 2);
        $expectedLength = $this->getIBANLength($countryCode);

        if ($expectedLength && strlen($iban) !== $expectedLength) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // Si un pays spécifique est requis
        if ($constraint->country && $countryCode !== $constraint->country) {
            $this->context->buildViolation('L\'IBAN doit être un IBAN {{ country }}.')
                ->setParameter('{{ country }}', $constraint->country)
                ->addViolation();
            return;
        }

        // Validation par algorithme de clé de contrôle (modulo 97)
        if (!$this->validateIBANChecksum($iban)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }

    /**
     * Valide la clé de contrôle IBAN (modulo 97)
     */
    private function validateIBANChecksum(string $iban): bool
    {
        // Déplace les 4 premiers caractères à la fin
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        // Remplace les lettres par leurs valeurs numériques (A=10, B=11, etc.)
        $numeric = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            if (ctype_alpha($char)) {
                $numeric .= (ord($char) - ord('A') + 10);
            } else {
                $numeric .= $char;
            }
        }

        // Calcule le modulo 97
        return $this->bcmod($numeric, 97) === '1';
    }

    /**
     * Modulo pour grands nombres (bcmath)
     */
    private function bcmod(string $number, int $modulus): string
    {
        if (function_exists('bcmod')) {
            return bcmod($number, (string) $modulus);
        }

        // Fallback si bcmath n'est pas disponible
        $result = 0;
        for ($i = 0; $i < strlen($number); $i++) {
            $result = ($result * 10 + (int)$number[$i]) % $modulus;
        }
        return (string) $result;
    }

    /**
     * Retourne la longueur attendue pour chaque pays
     */
    private function getIBANLength(string $countryCode): ?int
    {
        $lengths = [
            'AD' => 24, 'AE' => 23, 'AL' => 28, 'AT' => 20, 'AZ' => 28,
            'BA' => 20, 'BE' => 16, 'BG' => 22, 'BH' => 22, 'BR' => 29,
            'CH' => 21, 'CR' => 22, 'CY' => 28, 'CZ' => 24, 'DE' => 22,
            'DK' => 18, 'DO' => 28, 'EE' => 20, 'ES' => 24, 'FI' => 18,
            'FO' => 18, 'FR' => 27, 'GB' => 22, 'GE' => 22, 'GI' => 23,
            'GL' => 18, 'GR' => 27, 'GT' => 28, 'HR' => 21, 'HU' => 28,
            'IE' => 22, 'IL' => 23, 'IS' => 26, 'IT' => 27, 'JO' => 30,
            'KW' => 30, 'KZ' => 20, 'LB' => 28, 'LI' => 21, 'LT' => 20,
            'LU' => 20, 'LV' => 21, 'MC' => 27, 'MD' => 24, 'ME' => 22,
            'MK' => 19, 'MR' => 27, 'MT' => 31, 'MU' => 30, 'NL' => 18,
            'NO' => 15, 'PK' => 24, 'PL' => 28, 'PS' => 29, 'PT' => 25,
            'QA' => 29, 'RO' => 24, 'RS' => 22, 'SA' => 24, 'SE' => 24,
            'SI' => 19, 'SK' => 24, 'SM' => 27, 'TN' => 24, 'TR' => 26,
        ];

        return $lengths[$countryCode] ?? null;
    }
}
