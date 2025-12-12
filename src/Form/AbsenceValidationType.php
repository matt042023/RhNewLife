<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class AbsenceValidationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('action', ChoiceType::class, [
                'label' => 'Action',
                'choices' => [
                    'Valider la demande' => 'validate',
                    'Refuser la demande' => 'reject',
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'validate',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez choisir une action']),
                ],
                'attr' => [
                    'data-controller' => 'absence-validation',
                    'data-action' => 'change->absence-validation#onActionChange',
                ],
            ])
            ->add('rejectionReason', TextareaType::class, [
                'label' => 'Motif de refus',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Indiquez le motif du refus (obligatoire si refus)',
                    'data-absence-validation-target' => 'rejectionReason',
                ],
                'help' => 'Ce motif sera communiqué à l\'employé',
            ])
            ->add('adminComment', TextareaType::class, [
                'label' => 'Commentaire admin (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Commentaire interne non visible par l\'employé',
                ],
                'help' => 'Note interne non visible par l\'employé',
            ])
            ->add('forceWithoutJustification', CheckboxType::class, [
                'label' => 'Forcer la validation sans justificatif',
                'required' => false,
                'help' => 'Cochez cette case pour valider malgré un justificatif manquant ou non validé (action tracée)',
                'attr' => [
                    'class' => 'form-check-input',
                    'data-absence-validation-target' => 'forceCheckbox',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Not mapped to entity, handled manually in controller
            'data_class' => null,
        ]);
    }
}
