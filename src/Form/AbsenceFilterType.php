<?php

namespace App\Form;

use App\Entity\Absence;
use App\Entity\TypeAbsence;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AbsenceFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('absenceType', EntityType::class, [
                'class' => TypeAbsence::class,
                'choice_label' => 'label',
                'label' => 'Type d\'absence',
                'placeholder' => 'Tous les types',
                'required' => false,
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('t')
                        ->where('t.active = :active')
                        ->setParameter('active', true)
                        ->orderBy('t.label', 'ASC');
                },
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'placeholder' => 'Tous les statuts',
                'required' => false,
                'choices' => [
                    'En attente' => Absence::STATUS_PENDING,
                    'Validée' => Absence::STATUS_APPROVED,
                    'Refusée' => Absence::STATUS_REJECTED,
                    'Annulée' => Absence::STATUS_CANCELLED,
                    'Archivée' => Absence::STATUS_ARCHIVED,
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('justificationStatus', ChoiceType::class, [
                'label' => 'Statut justificatif',
                'placeholder' => 'Tous',
                'required' => false,
                'choices' => [
                    'Non requis' => Absence::JUSTIF_NOT_REQUIRED,
                    'En attente' => Absence::JUSTIF_PENDING,
                    'Fourni' => Absence::JUSTIF_PROVIDED,
                    'Validé' => Absence::JUSTIF_VALIDATED,
                    'Rejeté' => Absence::JUSTIF_REJECTED,
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('dateFrom', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('dateTo', DateType::class, [
                'label' => 'Au',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
