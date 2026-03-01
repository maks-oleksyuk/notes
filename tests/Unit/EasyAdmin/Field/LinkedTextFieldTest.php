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

        $this->assertSame('username', $linkedTextFieldDto->getProperty());
        $this->assertSame('User Name', $linkedTextFieldDto->getLabel());
        $this->assertSame('easyadmin/crud/field/linked_text.html.twig', $linkedTextFieldDto->getTemplatePath());
        $this->assertSame('field-text', $linkedTextFieldDto->getCssClass());
        $this->assertSame('col-md-6 col-xxl-5', $linkedTextFieldDto->getDefaultColumns());
        $this->assertNull($linkedTextFieldDto->getCustomOption(LinkedTextField::OPTION_RENDER_AS_LINK_TO_ENTITY));
    }

    public function testRenderAsLinkToEntitySetsCustomOption(): void
    {
        $field = LinkedTextField::new('username')->renderAsLinkToEntity()->getAsDto();

        $this->assertTrue($field->getCustomOption(LinkedTextField::OPTION_RENDER_AS_LINK_TO_ENTITY));
    }
}
