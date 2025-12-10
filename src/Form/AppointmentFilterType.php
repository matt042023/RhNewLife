<?php

namespace App\Form;

use App\Entity\RendezVous;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AppointmentFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'placeholder' => 'Tous les statuts',
                'choices' => [
                    'En attente' => RendezVous::STATUS_EN_ATTENTE,
                    'Confirmé' => RendezVous::STATUS_CONFIRME,
                    'Refusé' => RendezVous::STATUS_REFUSE,
                    'Annulé' => RendezVous::STATUS_ANNULE,
                    'Terminé' => RendezVous::STATUS_TERMINE,
                ],
                'attr' => [
                    'class' => 'form-select',
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'required' => false,
                'placeholder' => 'Tous les types',
                'choices' => [
                    'Convocation' => RendezVous::TYPE_CONVOCATION,
                    'Demande' => RendezVous::TYPE_DEMANDE,
                ],
                'attr' => [
                    'class' => 'form-select',
                ]
            ])
            ->add('dateFrom', DateType::class, [
                'label' => 'Date de début',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('dateTo', DateType::class, [
                'label' => 'Date de fin',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('participant', EntityType::class, [
                'class' => User::class,
                'label' => 'Participant',
                'required' => false,
                'placeholder' => 'Tous les participants',
                'choice_label' => function (User $user) {
                    return $user->getFullName();
                },
                'attr' => [
                    'class' => 'form-select',
                    'data-controller' => 'select2',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
