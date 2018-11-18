<?php

namespace App\Form;

use App\Entity\Room;
use SchoolIT\CommonBundle\Form\FieldsetType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class DeviceType extends AbstractType {
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder
            ->add('group_general', FieldsetType::class, [
                'legend' => 'label.general',
                'fields' => function(FormBuilderInterface $builder) {
                    $builder
                        ->add('name', TextType::class, [
                            'required' => true,
                            'label' => 'label.name'
                        ])
                        ->add('room', EntityType::class, [
                            'class' => Room::class,
                            'choice_label' => 'name',
                            'group_by' => function(Room $room) {
                                return $room->getCategory()->getName();
                            },
                            'required' => true,
                            'label' => 'label.rooms'
                        ])
                        ->add('type', EntityType::class, [
                            'class' => \App\Entity\DeviceType::class,
                            'choice_label' => 'name',
                            'required' => true,
                            'label' => 'label.devicetype'
                        ]);
                }
            ]);
    }
}