<?php

namespace App\Form;

use App\Entity\RendezVous;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AppointmentRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, [
                'label' => 'Objet de la demande',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Entretien individuel',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'objet est obligatoire'),
                    new Assert\Length(max: 255, maxMessage: 'L\'objet ne peut pas dépasser {{ limit }} caractères')
                ]
            ])
            ->add('recipient', EntityType::class, [
                'class' => User::class,
                'label' => 'Destinataire',
                'required' => true,
                'mapped' => false,
                'choice_label' => function (User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                'query_builder' => function (UserRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where('u.roles LIKE :role_admin OR u.roles LIKE :role_director')
                        ->setParameter('role_admin', '%"ROLE_ADMIN"%')
                        ->setParameter('role_director', '%"ROLE_DIRECTOR"%')
                        ->orderBy('u.lastName', 'ASC')
                        ->addOrderBy('u.firstName', 'ASC');
                },
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le destinataire est obligatoire')
                ]
            ])
            ->add('preferredDate', DateTimeType::class, [
                'label' => 'Date souhaitée (optionnelle)',
                'required' => false,
                'mapped' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'À définir par le destinataire',
                ],
                'help' => 'Vous pouvez proposer une date, elle sera confirmée ou modifiée par le destinataire',
                'constraints' => [
                    new Assert\GreaterThan('now', message: 'La date doit être dans le futur')
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Message',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Motif de la demande, informations complémentaires...',
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }
}
