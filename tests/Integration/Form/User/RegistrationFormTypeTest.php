<?php

declare(strict_types=1);

namespace App\Tests\Integration\Form\User;

use App\Entity\User;
use App\Form\User\RegistrationFormType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(RegistrationFormType::class)]
final class RegistrationFormTypeTest extends TypeTestCase
{
    /**
     * @return ValidatorExtension[]
     */
    #[\Override]
    protected function getExtensions(): array
    {
        return [new ValidatorExtension(Validation::createValidator())];
    }

    public function testFormFieldsExistAndHaveCorrectAttributes(): void
    {
        $view = $this->factory->create(RegistrationFormType::class, new User())->createView();

        $this->assertFieldAttributes($view->children['username'], [
            'autofocus' => true,
            'autocomplete' => 'username',
        ]);
        $this->assertFieldAttributes($view->children['password']->children['first'], [
            'autocomplete' => 'new-password',
            'placeholder' => 'Create a password',
        ]);
        $this->assertFieldAttributes($view->children['password']->children['second'], [
            'autocomplete' => 'new-password',
            'placeholder' => 'Confirm your password',
        ]);
    }

    public function testSubmitValidData(): void
    {
        $form = $this->submitRegistrationForm('Valid123!');

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
    }

    public function testPasswordTooShort(): void
    {
        $this->assertPasswordInvalid('Short1!', 'Password should be at least 8 characters');
    }

    public function testPasswordNoUppercase(): void
    {
        $this->assertPasswordInvalid('lowercase1!', 'Password should include at least one uppercase letter.');
    }

    public function testPasswordNoLowercase(): void
    {
        $this->assertPasswordInvalid('UPPERCASE1!', 'Password should include at least one lowercase letter.');
    }

    public function testPasswordNoDigit(): void
    {
        $this->assertPasswordInvalid('NoDigitPass', 'Password should include at least one number.');
    }

    public function testPasswordNoSpecialCharacter(): void
    {
        $this->assertPasswordInvalid('NoSpecial123', 'Password should include at least one special character.');
    }

    public function testPasswordMismatch(): void
    {
        $form = $this->factory->create(RegistrationFormType::class);
        $form->submit([
            'username' => 'test_user',
            'password' => [
                'first' => 'Mismatch1!',
                'second' => 'Mismatch2!',
            ],
        ]);

        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get('password')->getErrors(true)->count());
    }

    /**
     * @return FormInterface<?User>
     */
    private function submitRegistrationForm(string $password): FormInterface
    {
        $form = $this->factory->create(RegistrationFormType::class);
        $form->submit([
            'username' => 'test_user',
            'password' => [
                'first' => $password,
                'second' => $password,
            ],
        ]);

        return $form;
    }

    private function assertPasswordInvalid(string $password, string $expectedError): void
    {
        $form = $this->submitRegistrationForm($password);
        $this->assertFalse($form->isValid());
        $this->assertStringContainsString($expectedError, (string) $form->get('password')->getErrors(true));
    }

    /**
     * @param array<string, string|bool> $expected
     */
    private function assertFieldAttributes(FormView $fieldView, array $expected): void
    {
        $this->assertIsArray($fieldView->vars['attr']);
        $this->assertSame($expected, array_intersect_key($fieldView->vars['attr'], $expected));
    }
}
