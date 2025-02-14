<?php

namespace NorseBlue\ExtensibleObjects\Tests\Feature;

use Exception;
use NorseBlue\ExtensibleObjects\Exceptions\ClassNotExtensionMethodException;
use NorseBlue\ExtensibleObjects\Exceptions\ExtensionGuardedException;
use NorseBlue\ExtensibleObjects\Exceptions\ExtensionNotCallableException;
use NorseBlue\ExtensibleObjects\Exceptions\ExtensionNotFoundException;
use NorseBlue\ExtensibleObjects\Exceptions\MethodDefinedInClassException;
use NorseBlue\ExtensibleObjects\Tests\Helpers\ChildExtensionMethodReplacement;
use NorseBlue\ExtensibleObjects\Tests\Helpers\ChildObject;
use NorseBlue\ExtensibleObjects\Tests\Helpers\CreatableObject;
use NorseBlue\ExtensibleObjects\Tests\Helpers\CreatableObjectExtensionMethod;
use NorseBlue\ExtensibleObjects\Tests\Helpers\DynamicMethodUsingPrivateValue;
use NorseBlue\ExtensibleObjects\Tests\Helpers\DynamicMethodUsingProtectedValue;
use NorseBlue\ExtensibleObjects\Tests\Helpers\FooObject;
use NorseBlue\ExtensibleObjects\Tests\Helpers\GrandChildObject;
use NorseBlue\ExtensibleObjects\Tests\Helpers\GuardedExtensionMethod;
use NorseBlue\ExtensibleObjects\Tests\Helpers\GuardedObject;
use NorseBlue\ExtensibleObjects\Tests\Helpers\OtherExtensionMethod;
use NorseBlue\ExtensibleObjects\Tests\Helpers\SimpleObject;
use NorseBlue\ExtensibleObjects\Tests\Helpers\StaticExtensionMethod;
use NorseBlue\ExtensibleObjects\Tests\Helpers\StaticPropertyExtensionMethod;
use NorseBlue\ExtensibleObjects\Tests\Helpers\StaticPropertyObject;
use NorseBlue\ExtensibleObjects\Tests\TestCase;

class ExtensionMethodTest extends TestCase
{
    protected function setUp(): void
    {
        ChildObject::registerExtensionMethod('subtract_from_protected', ChildExtensionMethodReplacement::class);
        SimpleObject::registerExtensionMethod('add_to_private', DynamicMethodUsingPrivateValue::class);
        SimpleObject::registerExtensionMethod('subtract_from_protected', DynamicMethodUsingProtectedValue::class);
        SimpleObject::registerExtensionMethod('static_extension', StaticExtensionMethod::class);
        StaticPropertyObject::registerExtensionMethod(
            'static_property_extension',
            StaticPropertyExtensionMethod::class
        );
    }

    protected function tearDown(): void
    {
        ChildObject::unregisterExtensionMethod('subtract_from_protected');
        SimpleObject::unregisterExtensionMethod('add_to_private');
        SimpleObject::unregisterExtensionMethod('subtract_from_protected');
        SimpleObject::unregisterExtensionMethod('static_extension');
        StaticPropertyObject::unregisterExtensionMethod('static_property_extensions');
    }

    /** @test */
    public function cannot_override_guarded_method()
    {
        $this->assertFalse(GuardedObject::hasExtensionMethod('guarded'));
        GuardedObject::registerExtensionMethod('guarded', GuardedExtensionMethod::class, true);
        $this->assertTrue(GuardedObject::hasExtensionMethod('guarded'));

        try {
            GuardedObject::registerExtensionMethod('guarded', OtherExtensionMethod::class);
        } catch (Exception $e) {
            $this->assertInstanceOf(ExtensionGuardedException::class, $e);

            return;
        }

        $this->fail(ExtensionGuardedException::class . ' was not thrown.');
    }

    /** @test */
    public function cannot_unregister_guarded_method()
    {
        $this->assertFalse(GuardedObject::hasExtensionMethod('unregisterable'));
        GuardedObject::registerExtensionMethod('unregisterable', GuardedExtensionMethod::class, true);
        $this->assertTrue(GuardedObject::hasExtensionMethod('unregisterable'));

        try {
            GuardedObject::unregisterExtensionMethod('unregisterable');
        } catch (Exception $e) {
            $this->assertInstanceOf(ExtensionGuardedException::class, $e);

            return;
        }

        $this->fail(ExtensionGuardedException::class . ' was not thrown.');
    }

    /** @test */
    public function child_executes_own_extension_method()
    {
        $obj = new ChildObject();

        $result = $obj->subtract_from_protected(3);

        $this->assertEquals(-6, $result);
    }

    /** @test */
    public function child_executes_parent_extension_method()
    {
        $obj = new ChildObject();

        $result = $obj->add_to_private(3);

        $this->assertEquals(3, $result);
    }

    /** @test */
    public function child_object_inherits_parent_extension_methods()
    {
        $this->assertTrue(ChildObject::hasExtensionMethod('add_to_private'));
        $this->assertFalse(ChildObject::hasExtensionMethod('add_to_private', true));
        $this->assertTrue(ChildObject::hasExtensionMethod('subtract_from_protected'));

        $extensions = ChildObject::getExtensionMethods();
        $extensions_excluding_parent = ChildObject::getExtensionMethods(true);

        $this->assertCount(3, $extensions);
        $this->assertCount(1, $extensions_excluding_parent);

        $this->assertInstanceOf(
            DynamicMethodUsingPrivateValue::class,
            $extensions['add_to_private']['method']
        );
        $this->assertInstanceOf(
            ChildExtensionMethodReplacement::class,
            $extensions['subtract_from_protected']['method']
        );
        $this->assertInstanceOf(
            StaticExtensionMethod::class,
            $extensions['static_extension']['method']
        );

        $this->assertInstanceOf(
            ChildExtensionMethodReplacement::class,
            $extensions_excluding_parent['subtract_from_protected']['method']
        );
    }

    /** @test */
    public function creatable_extensible_object()
    {
        $this->assertFalse(CreatableObject::hasExtensionMethod('creatable'));
        CreatableObject::registerExtensionMethod('creatable', CreatableObjectExtensionMethod::class);
        $this->assertTrue(CreatableObject::hasExtensionMethod('creatable'));

        $creatable = CreatableObject::create();
        $this->assertEquals('created', $creatable->creatable());
    }

    /** @test */
    public function grand_child_inherits_extensible_methods()
    {
        $obj = new GrandChildObject();

        $result = $obj->subtract_from_protected(3);

        $this->assertEquals(-6, $result);
    }

    /** @test */
    public function it_checks_registered_extension_methods()
    {
        $this->assertTrue(SimpleObject::hasExtensionMethod('add_to_private'));
        $this->assertTrue(SimpleObject::hasExtensionMethod('subtract_from_protected'));
        $this->assertFalse(SimpleObject::hasExtensionMethod('nonexistent'));

        $extensions = SimpleObject::getExtensionMethods();

        $this->assertCount(3, $extensions);
        $this->assertArrayHasKey('add_to_private', $extensions);
        $this->assertArrayHasKey('subtract_from_protected', $extensions);
        $this->assertArrayHasKey('static_extension', $extensions);
        $this->assertInstanceOf(DynamicMethodUsingPrivateValue::class, $extensions['add_to_private']['method']);
        $this->assertInstanceOf(
            DynamicMethodUsingProtectedValue::class,
            $extensions['subtract_from_protected']['method']
        );
    }

    /** @test */
    public function it_executes_method_using_private_property_correctly()
    {
        $obj = new SimpleObject();

        $result = $obj->add_to_private(3);

        $this->assertEquals(3, $result);
    }

    /** @test */
    public function it_executes_method_using_protected_property_correctly()
    {
        $obj = new SimpleObject();

        $result = $obj->subtract_from_protected(3);

        $this->assertEquals(-3, $result);
    }

    /** @test */
    public function it_throws_exception_when_calling_not_existing_extension_method()
    {
        $obj = new SimpleObject();

        try {
            $obj->nonexistent();
        } catch (Exception $e) {
            $this->assertInstanceOf(ExtensionNotFoundException::class, $e);

            return;
        }

        $this->fail(ExtensionNotFoundException::class . ' was not thrown.');
    }

    /** @test */
    public function it_throws_exception_when_extension_method_class_is_not_extension_method()
    {
        try {
            SimpleObject::registerExtensionMethod('foo', FooObject::class);
        } catch (Exception $e) {
            $this->assertInstanceOf(ClassNotExtensionMethodException::class, $e);

            return;
        }

        $this->fail(ClassNotExtensionMethodException::class . ' was not thrown.');
    }

    /** @test */
    public function it_throws_exception_when_extension_method_is_not_callable()
    {
        try {
            SimpleObject::registerExtensionMethod('foo', 'not callable');
        } catch (Exception $e) {
            $this->assertInstanceOf(ExtensionNotCallableException::class, $e);

            return;
        }

        $this->fail(ExtensionNotCallableException::class . ' was not thrown.');
    }

    /** @test */
    public function it_throws_exception_when_extension_method_name_already_defined_as_class_function()
    {
        try {
            SimpleObject::registerExtensionMethod(
                'definedMethod',
                function () {
                    return 'already defined';
                }
            );
        } catch (Exception $e) {
            $this->assertInstanceOf(MethodDefinedInClassException::class, $e);

            return;
        }

        $this->fail(MethodDefinedInClassException::class . ' was not thrown.');
    }

    /** @test */
    public function static_extension_executes_as_expected()
    {
        $obj = new SimpleObject();

        $result1 = $obj::static_extension(3);
        $result2 = SimpleObject::static_extension(5);

        $this->assertEquals(9, $result1);
        $this->assertEquals(25, $result2);
    }

    /** @test */
    public function static_property_extension_executes_as_expected()
    {
        $result = StaticPropertyExtensionMethod::static_property_extension();

        $this->assertEquals('MY_VALUE', $result);
    }
}
