<?php

namespace App\Form;

use App\Entity\RendezVous;
use App\Entity\User;
use App\Entity\VisiteMedicale;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AppointmentMedicalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('visitType', ChoiceType::class, [
                'label' => 'Type de visite médicale',
                'required' => true,
                'mapped' => false,
                'choices' => [
                    'Visite d\'embauche' => VisiteMedicale::TYPE_EMBAUCHE,
                    'Visite périodique' => VisiteMedicale::TYPE_PERIODIQUE,
                    'Visite de reprise' => VisiteMedicale::TYPE_REPRISE,
                    'Visite à la demande' => VisiteMedicale::TYPE_DEMANDE,
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le type de visite est obligatoire'),
                ]
            ])
            ->add('participants', EntityType::class, [
                'class' => User::class,
                'label' => 'Éducateur concerné',
                'required' => true,
                'multiple' => false,
                'mapped' => false,
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('u')
                        ->where('u.status = :status')
                        ->setParameter('status', 'active')
                        ->orderBy('u.lastName', 'ASC');
                },
                'choice_label' => function (User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                'attr' => [
                    'class' => 'form-select',
                    'data-controller' => 'select2',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'éducateur est obligatoire'),
                ]
            ])
            ->add('startAt', DateTimeType::class, [
                'label' => 'Date et heure du rendez-vous',
                'required' => true,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'data-controller' => 'appointment-form',
                    'data-appointment-form-target' => 'startDate',
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
                    'max' => 240,
                    'data-appointment-form-target' => 'duration',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'La durée est obligatoire'),
                    new Assert\Positive(message: 'La durée doit être positive'),
                    new Assert\Range(
                        min: 15,
                        max: 240,
                        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} minutes'
                    )
                ]
            ])
            ->add('location', TextType::class, [
                'label' => 'Organisme médical / Lieu',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Médecine du Travail ACMS',
                ],
                'constraints' => [
                    new Assert\Length(max: 255)
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Notes / Observations préliminaires',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Informations complémentaires...',
                ]
            ])
            ->add('subject', TextType::class, [
                'label' => 'Objet du rendez-vous',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Sera généré automatiquement selon le type de visite',
                    'readonly' => true,
                ],
                'help' => 'L\'objet sera généré automatiquement : "Visite médicale - [Type]"'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }
}
