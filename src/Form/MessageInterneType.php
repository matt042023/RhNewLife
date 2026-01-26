<?php

namespace App\Form;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class MessageInterneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('destinataires', EntityType::class, [
                'class' => User::class,
                'query_builder' => function (UserRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where('u.status = :status')
                        ->setParameter('status', User::STATUS_ACTIVE)
                        ->orderBy('u.lastName', 'ASC')
                        ->addOrderBy('u.firstName', 'ASC');
                },
                'choice_label' => function (User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Destinataires',
                'attr' => [
                    'class' => 'glass-input',
                    'data-placeholder' => 'Selectionnez les destinataires...',
                ],
                'help' => 'Selectionnez un ou plusieurs utilisateurs',
                'constraints' => [],
            ])
            ->add('rolesCible', ChoiceType::class, [
                'choices' => [
                    'Tous les utilisateurs' => 'ROLE_USER',
                    'Administrateurs' => 'ROLE_ADMIN',
                    'Direction' => 'ROLE_DIRECTOR',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Ou envoyer par role (broadcast)',
                'help' => 'Alternative: envoyer a tous les utilisateurs d\'un role',
            ])
            ->add('sujet', TextType::class, [
                'label' => 'Sujet',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le sujet est obligatoire',
                        'groups' => ['message_interne'],
                    ]),
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'Le sujet ne peut pas depasser {{ limit }} caracteres',
                        'groups' => ['message_interne'],
                    ]),
                ],
                'attr' => [
                    'class' => 'glass-input w-full',
                    'placeholder' => 'Objet du message',
                ],
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Message',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le message est obligatoire',
                        'groups' => ['message_interne'],
                    ]),
                ],
                'attr' => [
                    'class' => 'glass-input w-full',
                    'rows' => 8,
                    'placeholder' => 'Votre message...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Use a specific validation group to avoid cascading User entity constraints
            'validation_groups' => ['message_interne'],
        ]);
    }
}
