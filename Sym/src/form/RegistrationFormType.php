<?php

namespace App\form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => false,
                'required' => false,
                'attr'  => ['id' => 'reg_first', 'autocomplete' => 'given-name', 'placeholder' => ' '],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'First name is required']),
                    new Assert\Length([
                        'min'        => 2,
                        'max'        => 50,
                        'minMessage' => 'First name must be at least 2 characters',
                        'maxMessage' => 'First name cannot exceed 50 characters',
                    ]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => false,
                'required' => false,
                'attr'  => ['id' => 'reg_last', 'autocomplete' => 'family-name', 'placeholder' => ' '],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Last name is required']),
                    new Assert\Length([
                        'min'        => 2,
                        'max'        => 50,
                        'minMessage' => 'Last name must be at least 2 characters',
                        'maxMessage' => 'Last name cannot exceed 50 characters',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => false,
                'required' => false,
                'attr'  => ['id' => 'reg_email', 'autocomplete' => 'email', 'placeholder' => ' '],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Email is required']),
                    new Assert\Email(['message' => 'Please enter a valid email address']),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type'          => PasswordType::class,
                'first_options' => [
                    'label' => false,
                    'attr'  => ['id' => 'reg_password', 'autocomplete' => 'new-password', 'placeholder' => ' '],
                    'constraints' => [
                        new Assert\NotBlank(['message' => 'Password is required']),
                        new Assert\Length([
                            'min'        => 8,
                            'minMessage' => 'Password must be at least 8 characters (include uppercase, lowercase & numbers)',
                        ]),
                        new Assert\Regex([
                            'pattern' => '/[0-9]/',
                            'message' => 'Password must contain at least one number',
                        ]),
                    ],
                ],
                'second_options' => [
                    'label' => false,
                    'attr'  => ['id' => 'reg_confirm', 'autocomplete' => 'new-password', 'placeholder' => ' '],
                ],
                'invalid_message' => 'Passwords do not match',
            ])
            ->add('phoneNumber', TextType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => ['id' => 'reg_phone', 'placeholder' => ' '],
                'constraints' => [
                    new Assert\Length([
                        'max'        => 20,
                        'maxMessage' => 'Phone number cannot exceed 20 characters',
                    ]),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Create account',
                'attr'  => ['class' => 'btn-primary', 'style' => 'display:none'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id'   => 'register',
        ]);
    }
}