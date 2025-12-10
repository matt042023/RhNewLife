<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AppointmentRefusalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('refusalReason', TextareaType::class, [
                'label' => 'Motif du refus',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Veuillez expliquer la raison du refus...',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le motif de refus est obligatoire'),
                    new Assert\Length(
                        min: 10,
                        max: 1000,
                        minMessage: 'Le motif doit contenir au moins {{ limit }} caractères',
                        maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères'
                    )
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Ce formulaire est utilisé pour la saisie du motif de refus uniquement
        ]);
    }
}
