<?php

declare(strict_types=1);

namespace App\Tests\Unit\EasyAdmin\Field;

use App\EasyAdmin\Field\LinkedTextField;
use PHPUnit\Framework\TestCase;

final class LinkedTextFieldTest extends TestCase
{
    public function testNewCreatesFieldInstance(): void
    {
        $linkedTextFieldDto = LinkedTextField::new('username', 'User Name')->getAsDto();

        self::assertSame('username', $linkedTextFieldDto->getProperty());
        self::assertSame('User Name', $linkedTextFieldDto->getLabel());
        self::assertSame('easyadmin/crud/field/linked_text.html.twig', $linkedTextFieldDto->getTemplatePath());
        self::assertSame('field-text', $linkedTextFieldDto->getCssClass());
        self::assertSame('col-md-6 col-xxl-5', $linkedTextFieldDto->getDefaultColumns());
        self::assertNull($linkedTextFieldDto->getCustomOption(LinkedTextField::OPTION_RENDER_AS_LINK_TO_ENTITY));
    }

    public function testRenderAsLinkToEntitySetsCustomOption(): void
    {
        $field = LinkedTextField::new('username')->renderAsLinkToEntity()->getAsDto();

        self::assertTrue($field->getCustomOption(LinkedTextField::OPTION_RENDER_AS_LINK_TO_ENTITY));
    }
}
