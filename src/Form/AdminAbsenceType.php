<?php

namespace App\Form;

use App\Entity\Absence;
use App\Entity\TypeAbsence as TypeAbsenceEntity;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class AdminAbsenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                'label' => 'Employé',
                'placeholder' => 'Sélectionnez un employé',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un employé']),
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('u')
                        ->where('u.status = :status')
                        ->setParameter('status', User::STATUS_ACTIVE)
                        ->orderBy('u.lastName', 'ASC')
                        ->addOrderBy('u.firstName', 'ASC');
                },
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('absenceType', EntityType::class, [
                'class' => TypeAbsenceEntity::class,
                'choice_label' => 'label',
                'label' => 'Type d\'absence',
                'placeholder' => 'Sélectionnez un type',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un type d\'absence']),
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('t')
                        ->where('t.active = :active')
                        ->setParameter('active', true)
                        ->orderBy('t.label', 'ASC');
                },
                'attr' => [
                    'class' => 'form-select',
                    'data-controller' => 'absence-form',
                    'data-action' => 'change->absence-form#onTypeChange',
                ],
            ])
            ->add('startAt', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Date de début',
                'constraints' => [
                    new NotBlank(['message' => 'La date de début est obligatoire']),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'data-absence-form-target' => 'startDate',
                    'data-action' => 'change->absence-form#onDateChange',
                ],
            ])
            ->add('endAt', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Date de fin',
                'constraints' => [
                    new NotBlank(['message' => 'La date de fin est obligatoire']),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'data-absence-form-target' => 'endDate',
                    'data-action' => 'change->absence-form#onDateChange',
                ],
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Motif (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Précisez le motif de l\'absence (optionnel)',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => Absence::STATUS_PENDING,
                    'Approuvée' => Absence::STATUS_APPROVED,
                    'Refusée' => Absence::STATUS_REJECTED,
                ],
                'data' => Absence::STATUS_APPROVED, // Par défaut approuvée pour création admin
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un statut']),
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('adminComment', TextareaType::class, [
                'label' => 'Commentaire administrateur (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Note interne pour l\'équipe RH',
                ],
            ])
            ->add('justificationFile', FileType::class, [
                'label' => 'Justificatif (si applicable)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader un fichier PDF, JPG ou PNG',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf,.jpg,.jpeg,.png',
                ],
                'help' => 'Formats acceptés : PDF, JPG, PNG (max 10 Mo)',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Absence::class,
        ]);
    }
}
