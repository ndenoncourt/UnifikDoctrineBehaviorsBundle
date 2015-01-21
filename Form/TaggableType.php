<?php

namespace Unifik\DoctrineBehaviorsBundle\Form;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use Unifik\DoctrineBehaviorsBundle\Form\ChoiceList\TaggableEntityLoader;
use Unifik\DoctrineBehaviorsBundle\Form\DataTransformer\DenormalizedEntityTransformer;
use Unifik\DoctrineBehaviorsBundle\ORM\Taggable\TaggableListener;
use Unifik\DoctrineBehaviorsBundle\ORM\Taggable\TagManager;

/**
 * This form type should be used for Taggable entities
 */
class TaggableType extends AbstractType
{
    /**
     * @var TagManager
     */
    protected $tagManager;

    /**
     * @var TaggableListener
     */
    protected $taggableListener;

    /**
     * @var DenormalizedEntityTransformer
     */
    protected $denormalizedEntityTransformer;

    /**
     * Constructor
     *
     * @param TagManager $tagManager
     */
    public function __construct(TagManager $tagManager, TaggableListener $taggableListener)
    {
        $this->tagManager = $tagManager;
        $this->taggableListener = $taggableListener;
        $this->denormalizedEntityTransformer = new DenormalizedEntityTransformer($this->tagManager);
    }

    /**
     * Returns the name of the parent type.
     *
     * @return string|null The name of the parent type if any, null otherwise.
     */
    public function getParent()
    {
        return 'choice';
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'taggable';
    }

    /**
     * Add options required by this form type
     *
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $that = $this;

        $choiceList = function (Options $options) use ($that) {
            return $that->getChoiceList($options);
        };

        $resolver->setRequired(['resource_type', 'locale']);

        $resolver->setDefaults(array(
            'use_global_tags' => true,
            'multiple' => true,
            'expanded' => false,
            'choice_list' => $choiceList,
            'class' => 'Unifik\DoctrineBehaviorsBundle\Entity\Tag',
            'mapped' => true,
            'required' => false
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer($this->denormalizedEntityTransformer);
        $this->tagManager->setLocale($options['locale']);

        // On Pre-Submit, create the news posted Tags otherwise the form won't be valid
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function($event) use ($options) {
            // Data when posted
            $tags = $event->getData();

            // Loop through the posted tags (if not numeric, it's a news Tag)
            if (is_array($tags)) {
                foreach ($tags as $key => $tagId) {
                    if (!is_numeric($tagId)) {
                        $entity = $event->getForm()->getParent()->getData();
                        $tag = $this->tagManager->loadOrCreateTag(
                                $tagId,
                                $options['use_global_tags'] ? null : $entity->getResourceType()
                        );
                        $tags[$key] = $tag->getId();
                    }
                }

                // Update the posted data with the newly created tags
                $event->setData($tags);
            }
        }, 900);

        // On Post-Submit, mark the entity to be saved on postFlush by the TaggableListener
        // Can't save right now because we need ID of entities not flushed yet (Denormalized Tables)
        $builder->addEventListener(FormEvents::POST_SUBMIT, function($event) {
            if ($event->getForm()->isValid()) {
                // Get the form entity
                $entity = $event->getForm()->getParent()->getData();
                $this->taggableListener->addEntityToSave($entity);
            }
        }, 900);
    }

    /**
     * Get the Taggable Entity Loader
     *
     * @param $options
     * @return TaggableEntityLoader
     */
    protected function getEntityLoader($options)
    {
        return new TaggableEntityLoader(
            $this->tagManager->getEm(),
            $options
        );
    }

    /**
     * Get the Choice List
     *
     * @param $options
     * @return EntityChoiceList
     */
    protected function getChoiceList($options)
    {
        $loader = $this->getEntityLoader($options);

        return new EntityChoiceList(
            $this->tagManager->getEm(),
            $options['class'],
            null,
            $loader
        );
    }
}
