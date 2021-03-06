<?php

/* Copyright [2011, 2012, 2013] da Universidade Federal de Juiz de Fora
 * Este arquivo é parte do programa Framework Maestro.
 * O Framework Maestro é um software livre; você pode redistribuí-lo e/ou 
 * modificá-lo dentro dos termos da Licença Pública Geral GNU como publicada 
 * pela Fundação do Software Livre (FSF); na versão 2 da Licença.
 * Este programa é distribuído na esperança que possa ser  útil, 
 * mas SEM NENHUMA GARANTIA; sem uma garantia implícita de ADEQUAÇÃO a qualquer
 * MERCADO ou APLICAÇÃO EM PARTICULAR. Veja a Licença Pública Geral GNU/GPL 
 * em português para maiores detalhes.
 * Você deve ter recebido uma cópia da Licença Pública Geral GNU, sob o título
 * "LICENCA.txt", junto com este programa, se não, acesse o Portal do Software
 * Público Brasileiro no endereço www.softwarepublico.gov.br ou escreva para a 
 * Fundação do Software Livre(FSF) Inc., 51 Franklin St, Fifth Floor, Boston, MA
 * 02110-1301, USA.
 */

namespace Maestro\Persistence;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Maestro;
use ReflectionClass;

/**
 * Brief Class Description.
 * Complete Class Description.
 */
class AnnotationConfigLoader
{

    private $manager;
    private $phpMaps = array();
    private $classMaps = array();

    public function __construct($manager)
    {
        $this->manager = $manager;
    }

    public function getLocation($className)
    {
        return array();
    }

    public function getMap($className)
    {
        /*
         * array(
            'class' => \get_called_class(),
            'database' => 'kancolle',
            'table' => '"user"',
            'attributes' => array(
                'id' => array('column' => 'id','key' => 'primary','idgenerator' => 'seq_user_id','type' => 'integer'),
                'login' => array('column' => 'login'),
                'email' => array('column' => 'email'),
                'passwordSalt' => array('column' => 'password_salt','type' => 'string'),
                'password' => array('column' => 'password','type' => 'string'),
            ),
            'associations' => array(
                'roles' => array('toClass' => '\auth\models\role', 'cardinality' => 'manyToMany' , 'associative' => 'user_role'),
            )
        );
         */

        $p = strrpos($className, '\\');
        if ($p === false) {
            return;
        }
        if (!isset($this->phpMaps[$className])) {
            //$classNameMap = substr($className, 0, $p) . "\\map" . substr($className, $p) . 'map';
            //mdump('-----------------'.$classNameMap);
            AnnotationRegistry::registerFile("/home/master/html/maestro2/vendor/doctrine/orm/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php");
            AnnotationRegistry::registerAutoloadNamespace("Maestro\Persistence\Annotation", Maestro\Manager::getHome());

            $reader = new AnnotationReader();
            $reflClass = new ReflectionClass(get_parent_class($className));
            $classAnnotations = $reader->getClassAnnotations($reflClass);
            $attributesAnnotations = [];
            foreach($reflClass->getProperties() as $reflectionProperty){
                $attributesAnnotations[$reflectionProperty->name] = $reader->getPropertyAnnotations($reflectionProperty);
            }
            $map = array();
            $map['class'] = $className;
            $map['database'] = $classAnnotations[0]->database;
            $map['table'] = str_replace('`','"',$classAnnotations[0]->table); //Temp postgres fix
            $map['attributes'] = $map['associations'] = [];
            foreach($attributesAnnotations as $attrName=>$annotations){
                $column = $association = [];
                foreach($annotations as $annotation){

                    if ($annotation instanceof Maestro\Persistence\Annotation\Association){
                        $association['cardinality'] = $annotation->cardinality;
                        $association['toClass'] = $annotation->toClass;
                        if($annotation instanceof Maestro\Persistence\Annotation\ManyToMany){
                            $association['associative'] = $annotation->associative;
                        }elseif($annotation instanceof Maestro\Persistence\Annotation\OneToMany){
                            $association['keys'] = "{$annotation->foreignKey}:{$annotation->refersTo}";
                        }
                        //$map['associations'][$attrName] = $association;
                    }
                    else{
                        if ($annotation instanceof Column){
                            $column['column'] = $annotation->name ?: $attrName;
                            $column['type'] = $annotation->type;
                        }
                        if($annotation instanceof Id){
                            $column['key'] = 'primary';
                        }
                        if($annotation instanceof Maestro\Persistence\Annotation\IdGenerator){
                            $column['idgenerator'] = $annotation->sequence; //T
                        }
                        if($annotation instanceof Maestro\Persistence\Annotation\Validator){
                            $column['validators'] = get_object_vars($annotation);
                        }
                        $map['attributes'][$attrName] = $column;
                    }
                }
            }
            $this->phpMaps[$className] = $map;
        }
        return $this->phpMaps[$className];
    }

    public function getClassMap($className)
    {
        if ($className == '') {mtracestack();}
        //$className = strtolower(trim($className));
        $classIndex = strtolower(trim($className));
        if ($className{0} == '\\') {
            $className = substr($className, 1);
        }
        if ($className == '') {
            return;
        }
        if (isset($this->classMaps[$classIndex])) {
            return $this->classMaps[$classIndex];
        }
        $map = $this->getMap($className);
        //var_dump($map);
        $database = $map['database'];
        $classMap = new \Maestro\Persistence\Map\ClassMap($className, $database);
        $classMap->setDatabaseName($database);
        $classMap->setTableName($map['table']);

        if (isset($map['extends'])) {
            $classMap->setSuperClassName($map['extends']);
        }

        $config = $className::config();

        $attributes = $map['attributes'];
        foreach ($attributes as $attributeName => $attr) {
            $attributeMap = new \Maestro\Persistence\Map\AttributeMap($attributeName, $classMap);
            if (isset($attr['index'])) {
                $attributeMap->setIndex($attr['index']);
            }

            $type = isset($attr['type']) ? strtolower($attr['type']) : 'string';
            $attributeMap->setType($type);
            $plataformTypedAttributes = $classMap->getDb()->getPlatform()->getTypedAttributes();
            $attributeMap->setHandled(strpos($plataformTypedAttributes, $type) !== false);
            if ($config['converters'][$attributeName]) {
                $attributeMap->setConverter($config['converters'][$attributeName]);
            }

            $attributeMap->setColumnName($attr['column']? : $attributeName);
            $attributeMap->setAlias($attr['alias']? : $attributeName);
            $attributeMap->setKeyType($attr['key'] ? : 'none');
            $attributeMap->setIdGenerator($attr['idgenerator']);

            if (($attr['key'] == 'reference') && ($classMap->getSuperClassMap() != NULL)) {
                $referenceAttribute = $classMap->getSuperClassMap()->getAttributeMap($attributeName);
                if ($referenceAttribute) {
                    $attributeMap->setReference($referenceAttribute);
                }
            }
            $classMap->addAttributeMap($attributeMap);
        }

        $this->classMaps[$classIndex] = $classMap;

        if ($referenceAttribute) {
            // set superAssociationMap
            $attributeName = $referenceAttribute->getName();
            $superClassName = $classMap->getSuperClassMap()->getName();
            $superAssociationMap = new \Maestro\Persistence\Map\AssociationMap($classMap, $superClassName);
            $superAssociationMap->setToClassName($superClassName);
            $superAssociationMap->setToClassMap($classMap->getSuperClassMap());
            $superAssociationMap->setCardinality('oneToOne');
            $superAssociationMap->addKeys($attributeName, $attributeName);
            $superAssociationMap->setKeysAttributes();
            $classMap->setSuperAssociationMap($superAssociationMap);
        }

        $associations = $map['associations'];
        if (isset($associations)) {

            $fromClassMap = $classMap;
            foreach ($associations as $associationName => $association) {
                $toClass = $association['toClass'];
                $associationMap = new \Maestro\Persistence\Map\AssociationMap($classMap, $associationName);
                $associationMap->setToClassName($toClass);
                //$associationMap->setTargetAttributeName($associationName);
                $associationMap->setDeleteAutomatic($association['deleteAutomatic']);
                $associationMap->setSaveAutomatic($association['saveAutomatic']);
                $associationMap->setRetrieveAutomatic($association['retrieveAutomatic']);
                //$associationMap->setJoinAutomatic($association['joinAutomatic']);
                $autoAssociation = (strtolower($className) == strtolower($toClass));
                if (!$autoAssociation) {
                    $autoAssociation = (strtolower($className) == strtolower(substr($toClass, 1)));
                }
                $associationMap->setAutoAssociation($autoAssociation);
                if (isset($association['index'])) {
                    $associationMap->setIndexAttribute($association['index']);
                }
                $associationMap->setCardinality($association['cardinality']);
                if ($association['cardinality'] == 'manyToMany') {
                    $associationMap->setAssociativeTable($association['associative']);
                } else {
                    $arrayKeys = explode(',', $association['keys']);
                    foreach ($arrayKeys as $keys) {
                        $key = explode(':', $keys);
                        $associationMap->addKeys($key[0], $key[1]);
                    }
                }

                if (isset($association['order'])) {
                    $order = array();
                    $orderAttributes = explode(',', $association['order']);
                    foreach ($orderAttributes as $orderAttr) {
                        $o = explode(' ', $orderAttr);
                        $ascend = (substr($o[1], 0, 3) == 'asc');
                        $order[] = array($o[0], $ascend);
                    }
                    if (count($order)) {
                        $associationMap->setOrder($order);
                    }
                }

                $fromClassMap->putAssociationMap($associationMap);
            }
        }
        return $classMap;
    }

}

?>