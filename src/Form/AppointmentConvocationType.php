<?php

namespace App\Form;

use App\Entity\RendezVous;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AppointmentConvocationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, [
                'label' => 'Objet de la convocation',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Réunion d\'équipe',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'objet est obligatoire'),
                    new Assert\Length(max: 255, maxMessage: 'L\'objet ne peut pas dépasser {{ limit }} caractères')
                ]
            ])
            ->add('startAt', DateTimeType::class, [
                'label' => 'Date et heure',
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
                    'max' => 480,
                    'data-appointment-form-target' => 'duration',
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
            ->add('participants', EntityType::class, [
                'class' => User::class,
                'label' => 'Participants',
                'required' => true,
                'multiple' => true,
                'mapped' => false,
                'choice_label' => function (User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                'attr' => [
                    'class' => 'form-select',
                    'data-appointment-form-target' => 'participants',
                    'data-controller' => 'select2',
                ],
                'constraints' => [
                    new Assert\Count(
                        min: 1,
                        minMessage: 'Au moins un participant est requis'
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
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Ordre du jour, informations complémentaires...',
                ]
            ])
            ->add('createsAbsence', CheckboxType::class, [
                'label' => 'Créer une absence automatique pour les participants',
                'required' => false,
                'attr' => [
                    'class' => 'form-checkbox',
                ],
                'help' => 'Une absence de type "Réunion" sera automatiquement créée pour chaque participant'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }
}
