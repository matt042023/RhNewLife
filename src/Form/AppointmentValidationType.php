<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AppointmentValidationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('scheduledAt', DateTimeType::class, [
                'label' => 'Date et heure confirmée',
                'required' => true,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'La date est obligatoire'),
                    new Assert\GreaterThan('now', message: 'La date doit être dans le futur')
                ]
            ])
            ->add('durationMinutes', IntegerType::class, [
                'label' => 'Durée (minutes)',
                'required' => true,
                'data' => 60,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 15,
                    'max' => 480,
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'La durée est obligatoire'),
                    new Assert\Positive(message: 'La durée doit être positive'),
                    new Assert\Range(
                        min: 15,
                        max: 480,
                        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} minutes'
                    )
                ]
            ])
            ->add('location', TextType::class, [
                'label' => 'Lieu',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Salle de réunion A',
                ],
                'constraints' => [
                    new Assert\Length(max: 255)
                ]
            ])
            ->add('adminComment', TextareaType::class, [
                'label' => 'Commentaire (optionnel)',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Informations complémentaires pour le demandeur...',
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Note: Ce formulaire n'est pas directement mappé à RendezVous
            // car il contient des champs spécifiques à la validation
        ]);
    }
}
