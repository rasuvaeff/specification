<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\Specification;
use Rasuvaeff\Specification\SpecificationVisitor;
use ReflectionClass;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

#[Test]
#[CoversNothing]
final class SpecificationTest
{
    public function interfaceContract(): void
    {
        $reflection = new ReflectionClass(objectOrClass: Specification::class);

        Assert::true($reflection->isInterface());

        $methods = $reflection->getMethods();
        Assert::count($methods, 1);

        Assert::true($reflection->hasMethod(name: 'accept'));
        $acceptMethod = $reflection->getMethod(name: 'accept');

        $returnType = $acceptMethod->getReturnType();
        Assert::null($returnType);

        $methodDocComment = $acceptMethod->getDocComment();
        Assert::true(is_string($methodDocComment));
        Assert::string($methodDocComment)->contains('@param SpecificationVisitor<T> $visitor');
        Assert::string($methodDocComment)->contains('@return T');

        $parameters = $acceptMethod->getParameters();
        Assert::count($parameters, 1);

        $firstParam = $parameters[0];
        Assert::same($firstParam->getName(), 'visitor');
        $paramType = $firstParam->getType();
        Assert::instanceOf($paramType, \ReflectionNamedType::class);
        Assert::same($paramType->getName(), SpecificationVisitor::class);
        Assert::false($firstParam->isOptional());
        Assert::false($firstParam->isDefaultValueAvailable());
        Assert::false($firstParam->allowsNull());
    }

    public function templateAnnotation(): void
    {
        $reflection = new ReflectionClass(objectOrClass: Specification::class);
        $docComment = $reflection->getDocComment();

        Assert::true(is_string($docComment));
        Assert::string($docComment)->contains('@template T');
    }

    public function noAdditionalPublicMethods(): void
    {
        $reflection = new ReflectionClass(objectOrClass: Specification::class);
        $methods = $reflection->getMethods();

        Assert::count($methods, 1);
        Assert::same($methods[0]->getName(), 'accept');
    }

    public function interfaceHierarchy(): void
    {
        $reflection = new ReflectionClass(objectOrClass: Specification::class);

        $interfaces = $reflection->getInterfaceNames();
        Assert::blank($interfaces);
        Assert::true($reflection->isInterface());
    }
}
