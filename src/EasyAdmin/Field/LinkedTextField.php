<?php

declare(strict_types=1);

namespace App\EasyAdmin\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Contracts\Translation\TranslatableInterface;

final class LinkedTextField implements FieldInterface
{
    use FieldTrait;

    public const string OPTION_RENDER_AS_LINK_TO_ENTITY = 'renderAsLinkToEntity';

    public static function new(string $propertyName, TranslatableInterface|bool|string|null $label = null): self
    {
        return new self()
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('easyadmin/crud/field/linked_text.html.twig')
            ->setFormType(TextType::class)
            ->addCssClass('field-text')
            ->setDefaultColumns('col-md-6 col-xxl-5');
    }

    public function renderAsLinkToEntity(): self
    {
        return $this->setCustomOption(self::OPTION_RENDER_AS_LINK_TO_ENTITY, true);
    }
}
