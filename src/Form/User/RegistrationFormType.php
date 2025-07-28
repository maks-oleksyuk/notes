<?php

declare(strict_types=1);

namespace App\Form\User;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * @extends AbstractType<User>
 */
final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'attr' => [
                    'autofocus' => true,
                    'autocomplete' => 'username',
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => false,
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'Create a password',
                    ],
                ],
                'second_options' => [
                    'label' => false,
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'Confirm your password',
                    ],
                ],
                'constraints' => [
                    new Length(
                        min: 8,
                        max: 32,
                        minMessage: 'Password should be at least {{ limit }} characters',
                        maxMessage: 'Password should not be longer than {{ limit }} characters.',
                    ),
                    new Regex(
                        pattern: '/[A-Z]/',
                        message: 'Password should include at least one uppercase letter.',
                    ),
                    new Regex(
                        pattern: '/[a-z]/',
                        message: 'Password should include at least one lowercase letter.',
                    ),
                    new Regex(
                        pattern: '/\d/',
                        message: 'Password should include at least one number.',
                    ),
                    new Regex(
                        pattern: '/[\W_]/',
                        message: 'Password should include at least one special character.',
                    ),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Register',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
