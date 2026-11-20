<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];
    /** @var array<string,mixed> */
    private array $data;
    /** @var array<string,string|array<int,string>> */
    private array $rules;

    /**
     * @param array<string,mixed> $data
     * @param array<string,string|array<int,string>> $rules
     */
    public static function make(array $data, array $rules): self
    {
        $validator = new self();
        $validator->data = $data;
        $validator->rules = $rules;

        foreach ($rules as $field => $fieldRules) {
            $rulesList = is_array($fieldRules) ? $fieldRules : explode('|', (string) $fieldRules);
            $value = $data[$field] ?? null;
            $required = in_array('required', $rulesList, true);

            if (self::isEmpty($value)) {
                if ($required) {
                    $validator->addError($field, 'O campo ' . $validator->label($field) . ' é obrigatório.');
                }
                continue;
            }

            if (is_array($value)) {
                $validator->addError($field, 'O campo ' . $validator->label($field) . ' é inválido.');
                continue;
            }

            foreach ($rulesList as $rule) {
                if ($rule === 'required' || $rule === '') {
                    continue;
                }

                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $validator->apply($field, (string) $value, $name, $param);

                if (isset($validator->errors[$field])) {
                    break;
                }
            }
        }

        return $validator;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors === [] ? null : (string) reset($this->errors);
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        $result = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $value = $this->data[$field];
                $result[$field] = is_string($value) ? trim($value) : $value;
            }
        }

        return $result;
    }

    private function apply(string $field, string $value, string $name, ?string $param): void
    {
        $label = $this->label($field);

        switch ($name) {
            case 'string':
                break;
            case 'email':
                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $this->addError($field, 'Informe um e-mail válido.');
                }
                break;
            case 'min':
                if (mb_strlen($value) < (int) $param) {
                    $this->addError($field, sprintf('O campo %s deve ter pelo menos %d caracteres.', $label, (int) $param));
                }
                break;
            case 'max':
                if (mb_strlen($value) > (int) $param) {
                    $this->addError($field, sprintf('O campo %s deve ter no máximo %d caracteres.', $label, (int) $param));
                }
                break;
            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, 'O campo ' . $label . ' deve ser numérico.');
                }
                break;
            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, 'O campo ' . $label . ' deve ser um número inteiro.');
                }
                break;
            case 'min_value':
                if (!is_numeric($value) || (float) $value < (float) $param) {
                    $this->addError($field, sprintf('O campo %s deve ser maior ou igual a %s.', $label, $param));
                }
                break;
            case 'max_value':
                if (!is_numeric($value) || (float) $value > (float) $param) {
                    $this->addError($field, sprintf('O campo %s deve ser menor ou igual a %s.', $label, $param));
                }
                break;
            case 'in':
                $allowed = explode(',', (string) $param);
                if (!in_array($value, $allowed, true)) {
                    $this->addError($field, 'O valor informado para ' . $label . ' é inválido.');
                }
                break;
            case 'confirmed':
                $confirmation = $this->data[$field . '_confirmation'] ?? null;
                if (!is_string($confirmation) || !hash_equals($value, $confirmation)) {
                    $this->addError($field, 'A confirmação de ' . $label . ' não confere.');
                }
                break;
            case 'date':
                if (strtotime($value) === false) {
                    $this->addError($field, 'Informe uma data válida para ' . $label . '.');
                }
                break;
            case 'regex':
                if ($param === null || @preg_match($param, $value) !== 1) {
                    $this->addError($field, 'O formato do campo ' . $label . ' é inválido.');
                }
                break;
            case 'unique':
                $this->checkUnique($field, $value, (string) $param);
                break;
            case 'boolean':
                if (!in_array($value, ['0', '1', 'true', 'false', 'on'], true)) {
                    $this->addError($field, 'O campo ' . $label . ' é inválido.');
                }
                break;
        }
    }

    private function checkUnique(string $field, string $value, string $param): void
    {
        $parts = explode(',', $param);
        $table = $parts[0] ?? '';
        $column = $parts[1] ?? $field;
        $exceptId = isset($parts[2]) ? (int) $parts[2] : 0;

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return;
        }

        $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = ?', $table, $column);
        $params = [$value];

        if ($exceptId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            $this->addError($field, 'O valor informado para ' . $this->label($field) . ' já está em uso.');
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    private function label(string $field): string
    {
        return mb_strtolower(str_replace(['_', '-'], ' ', $field));
    }

    private static function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }
}
