<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.phpdoctrine.org>.
 */

#namespace Doctrine::ORM::Internal;

/**
 * The metadata factory is used to create ClassMetadata objects that contain all the
 * metadata mapping informations of a class.
 *
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Roman Borschel <roman@code-factory.org>
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version     $Revision$
 * @link        www.phpdoctrine.org
 * @since       2.0
 * @todo Rename to ClassDescriptorFactory.
 */
class Doctrine_ORM_Mapping_ClassMetadataFactory
{
    protected $_em;
    protected $_driver;
    
    /**
     * The already loaded metadata objects.
     */
    protected $_loadedMetadata = array();
    
    /**
     * Constructor.
     * Creates a new factory instance that uses the given EntityManager and metadata driver
     * implementations.
     *
     * @param $conn    The connection to use.
     * @param $driver  The metadata driver to use.
     */
    public function __construct(Doctrine_ORM_EntityManager $em, $driver)
    {
        $this->_em = $em;
        $this->_driver = $driver;
    }
    
    /**
     * Returns the metadata object for a class.
     *
     * @param string $className  The name of the class.
     * @return Doctrine_Metadata
     */
    public function getMetadataFor($className)
    {        
        if (isset($this->_loadedMetadata[$className])) {
            return $this->_loadedMetadata[$className];
        }
        $this->_loadClasses($className, $this->_loadedMetadata);
        
        return $this->_loadedMetadata[$className];
    }
    
    /**
     * Loads the metadata of the class in question and all it's ancestors whose metadata
     * is still not loaded.
     *
     * @param string $name   The name of the class for which the metadata should get loaded.
     * @param array  $tables The metadata collection to which the loaded metadata is added.
     */
    protected function _loadClasses($name, array &$classes)
    {
        $parentClass = $name;
        $parentClasses = array();
        $loadedParentClass = false;
        while ($parentClass = get_parent_class($parentClass)) {
            if ($parentClass == 'Doctrine_ORM_Entity') {
                break;
            }
            if (isset($classes[$parentClass])) {
                $loadedParentClass = $parentClass;
                break;
            }
            $parentClasses[] = $parentClass;
        }
        $parentClasses = array_reverse($parentClasses);
        $parentClasses[] = $name;
        
        if ($loadedParentClass) {
            $class = $classes[$loadedParentClass];
        } else {
            $rootClassOfHierarchy = count($parentClasses) > 0 ? array_shift($parentClasses) : $name;
            $class = new Doctrine_ORM_Mapping_ClassMetadata($rootClassOfHierarchy, $this->_em);
            $this->_loadMetadata($class, $rootClassOfHierarchy);
            $classes[$rootClassOfHierarchy] = $class;
        }
        
        if (count($parentClasses) == 0) {
            return $class;
        }
        
        // load metadata of subclasses
        // -> child1 -> child2 -> $name
        
        // Move down the hierarchy of parent classes, starting from the topmost class
        $parent = $class;
        foreach ($parentClasses as $subclassName) {
            $subClass = new Doctrine_ORM_Mapping_ClassMetadata($subclassName, $this->_em);
            $subClass->setInheritanceType($parent->getInheritanceType(), $parent->getInheritanceOptions());
            $this->_addInheritedFields($subClass, $parent);
            $this->_addInheritedRelations($subClass, $parent);
            $this->_loadMetadata($subClass, $subclassName);
            if ($parent->isInheritanceTypeSingleTable()) {
                $subClass->setTableName($parent->getTableName());
            }
            $classes[$subclassName] = $subClass;
            $parent = $subClass;
        }
    }
    
    /**
     * Adds inherited fields to the subclass mapping.
     *
     * @param Doctrine::ORM::Mapping::ClassMetadata $subClass
     * @param Doctrine::ORM::Mapping::ClassMetadata $parentClass
     * @return void
     */
    protected function _addInheritedFields($subClass, $parentClass)
    {
        foreach ($parentClass->getFieldMappings() as $fieldName => $mapping) {
            if ( ! isset($mapping['inherited'])) {
                $mapping['inherited'] = $parentClass->getClassName();
            }
            $subClass->addFieldMapping($fieldName, $mapping);
        }
    }
    
    /**
     * Adds inherited associations to the subclass mapping.
     *
     * @param unknown_type $subClass
     * @param unknown_type $parentClass
     */
    protected function _addInheritedRelations($subClass, $parentClass) 
    {
        foreach ($parentClass->getAssociationMappings() as $fieldName => $mapping) {
            $subClass->addAssociationMapping($name, $mapping);
        }
    }
    
    /**
     * Loads the metadata of a specified class.
     *
     * @param Doctrine_ClassMetadata $class  The container for the metadata.
     * @param string $name  The name of the class for which the metadata will be loaded.
     */
    protected function _loadMetadata(Doctrine_ORM_Mapping_ClassMetadata $class, $name)
    {
        if ( ! class_exists($name) || empty($name)) {
            throw new Doctrine_Exception("Couldn't find class " . $name . ".");
        }

        $names = array();
        $className = $name;
        // get parent classes
        //TODO: Skip Entity types MappedSuperclass/Transient
        do {
            if ($className === 'Doctrine_ORM_Entity') {
                break;
            } else if ($className == $name) {
                continue;
            }
            $names[] = $className;
        } while ($className = get_parent_class($className));

        if ($className === false) {
            throw new Doctrine_ClassMetadata_Factory_Exception("Unknown component '$className'.");
        }

        // save parents
        $class->setParentClasses($names);

        // load user-specified mapping metadata through the driver
        $this->_driver->loadMetadataForClass($name, $class);
        
        // set default table name, if necessary
        $tableName = $class->getTableName();
        if ( ! isset($tableName)) {
            $class->setTableName(Doctrine::tableize($class->getClassName()));
        }
        
        $class->completeIdentifierMapping();
        
        return $class;
    }
    
}


