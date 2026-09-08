<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Enums;

enum FieldType: string
{
    case Logo = 'logo';
    case Header = 'header';
    case Paragraph = 'paragraph';
    case Input = 'input';
    case Dropdown = 'dropdown';
    case Checkbox = 'checkbox';
    case Phone = 'phone';
    case FirstName = 'first_name';
    case LastName = 'last_name';

    public function label(): string
    {
        return match ($this) {
            self::Logo => 'Logo',
            self::Header => 'Header Field',
            self::Paragraph => 'Paragraph Field',
            self::Input => 'Input Field',
            self::Dropdown => 'Dropdown Field',
            self::Checkbox => 'Checkbox Field',
            self::Phone => 'WhatsApp Number',
            self::FirstName => 'First Name',
            self::LastName => 'Last Name',
        };
    }

    public function isAlwaysTop(): bool
    {
        return match ($this) {
            self::Logo, self::Header, self::Phone, self::FirstName, self::LastName => true,
            default => false,
        };
    }

    public function isRequiredByDefault(): bool
    {
        return match ($this) {
            self::Logo, self::Header, self::Phone, self::FirstName, self::LastName => true,
            default => false,
        };
    }

    public function hasOptions(): bool
    {
        return $this === self::Dropdown;
    }

    /**
     * Whether this field type is locked (cannot be removed by the user).
     */
    public function isLocked(): bool
    {
        return $this === self::Phone;
    }

    /**
     * Whether this field is a default field pre-populated on new forms.
     */
    public function isDefault(): bool
    {
        return match ($this) {
            self::Phone, self::FirstName, self::LastName => true,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        $base = [
            'type' => $this->value,
            'label' => $this->label(),
            'required' => $this->isRequiredByDefault(),
            'placeholder' => '',
            'text' => '',
            'required_message' => '',
        ];

        if ($this->hasOptions()) {
            $base['options'] = ['Option 1', 'Option 2'];
        }

        if ($this === self::Logo) {
            $base['image_path'] = null;
        }

        if ($this->isLocked()) {
            $base['locked'] = true;
        }

        return $base;
    }

    /**
     * The 3 default fields pre-populated on every new form.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultFields(): array
    {
        return [
            self::Phone->defaultConfig(),
            self::FirstName->defaultConfig(),
            self::LastName->defaultConfig(),
        ];
    }

    /**
     * @return list<self>
     */
    public static function availableForBuilder(): array
    {
        return [
            self::Logo,
            self::Header,
            self::Paragraph,
            self::Input,
            self::Dropdown,
            self::Checkbox,
        ];
    }
}
