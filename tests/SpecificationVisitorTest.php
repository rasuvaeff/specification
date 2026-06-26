<?php

declare(strict_types=1);

namespace Rasuvaeff\Specification\Tests;

use Rasuvaeff\Specification\ComparisonSpecification;
use Rasuvaeff\Specification\CompositeSpecification;
use Rasuvaeff\Specification\NotSpecification;
use Rasuvaeff\Specification\OrConditionSpecification;
use Rasuvaeff\Specification\OrSpecification;
use Rasuvaeff\Specification\RawSpecification;
use Rasuvaeff\Specification\SpecificationVisitor;
use ReflectionClass;
use ReflectionMethod;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

#[Test]
#[CoversNothing]
final class SpecificationVisitorTest
{
    public function interfaceContract(): void
    {
        $reflection = new ReflectionClass(objectOrClass: SpecificationVisitor::class);

        Assert::true($reflection->isInterface());

        $docComment = $reflection->getDocComment();
        Assert::true(is_string($docComment));
        Assert::string($docComment)->contains('@template T');

        $methods = $reflection->getMethods();
        Assert::count($methods, 9);

        Assert::true($reflection->hasMethod(name: 'visitComparison'));
        $visitComparisonMethod = $reflection->getMethod(name: 'visitComparison');

        $returnType = $visitComparisonMethod->getReturnType();
        Assert::null($returnType);

        $methodDocComment = $visitComparisonMethod->getDocComment();
        Assert::true(is_string($methodDocComment));
        Assert::string($methodDocComment)->contains('@return T');

        $parameters = $visitComparisonMethod->getParameters();
        Assert::count($parameters, 1);

        $firstParam = $parameters[0];
        Assert::same($firstParam->getName(), 'specification');
        $paramType = $firstParam->getType();
        Assert::instanceOf($paramType, \ReflectionNamedType::class);
        Assert::same($paramType->getName(), ComparisonSpecification::class);
        Assert::false($firstParam->isOptional());
        Assert::false($firstParam->isDefaultValueAvailable());
        Assert::false($firstParam->allowsNull());

        Assert::true($reflection->hasMethod(name: 'visitComposite'));
        $visitCompositeMethod = $reflection->getMethod(name: 'visitComposite');
        Assert::null($visitCompositeMethod->getReturnType());
        $methodDocComment = $visitCompositeMethod->getDocComment();
        Assert::true(is_string($methodDocComment));
        Assert::string($methodDocComment)->contains('@return T');
        $parameters = $visitCompositeMethod->getParameters();
        Assert::count($parameters, 1);
        $firstParam = $parameters[0];
        Assert::same($firstParam->getName(), 'specification');
        $paramType = $firstParam->getType();
        Assert::instanceOf($paramType, \ReflectionNamedType::class);
        Assert::same($paramType->getName(), CompositeSpecification::class);
        Assert::false($firstParam->isOptional());
        Assert::false($firstParam->isDefaultValueAvailable());
        Assert::false($firstParam->allowsNull());

        Assert::true($reflection->hasMethod(name: 'visitNot'));
        $visitNotMethod = $reflection->getMethod(name: 'visitNot');
        Assert::null($visitNotMethod->getReturnType());
        $methodDocComment = $visitNotMethod->getDocComment();
        Assert::true(is_string($methodDocComment));
        Assert::string($methodDocComment)->contains('@return T');
        $parameters = $visitNotMethod->getParameters();
        Assert::count($parameters, 1);
        $firstParam = $parameters[0];
        Assert::same($firstParam->getName(), 'specification');
        $paramType = $firstParam->getType();
        Assert::instanceOf($paramType, \ReflectionNamedType::class);
        Assert::same($paramType->getName(), NotSpecification::class);
        Assert::false($firstParam->isOptional());
        Assert::false($firstParam->isDefaultValueAvailable());
        Assert::false($firstParam->allowsNull());

        Assert::true($reflection->hasMethod(name: 'visitOr'));
        $visitOrMethod = $reflection->getMethod(name: 'visitOr');
        Assert::null($visitOrMethod->getReturnType());
        $methodDocComment = $visitOrMethod->getDocComment();
        Assert::true(is_string($methodDocComment));
        Assert::string($methodDocComment)->contains('@return T');
        $parameters = $visitOrMethod->getParameters();
        Assert::count($parameters, 1);
        $firstParam = $parameters[0];
        Assert::same($firstParam->getName(), 'specification');
        $paramType = $firstParam->getType();
        Assert::instanceOf($paramType, \ReflectionNamedType::class);
        Assert::same($paramType->getName(), OrSpecification::class);
        Assert::false($firstParam->isOptional());
        Assert::false($firstParam->isDefaultValueAvailable());
        Assert::false($firstParam->allowsNull());

        Assert::true($reflection->hasMethod(name: 'visitOrCondition'));
        $visitOrConditionMethod = $reflection->getMethod(name: 'visitOrCondition');
        Assert::null($visitOrConditionMethod->getReturnType());
        $methodDocComment = $visitOrConditionMethod->getDocComment();
        Assert::true(is_string($methodDocComment));
        Assert::string($methodDocComment)->contains('@return T');
        $parameters = $visitOrConditionMethod->getParameters();
        Assert::count($parameters, 1);
        $firstParam = $parameters[0];
        Assert::same($firstParam->getName(), 'specification');
        $paramType = $firstParam->getType();
        Assert::instanceOf($paramType, \ReflectionNamedType::class);
        Assert::same($paramType->getName(), OrConditionSpecification::class);
        Assert::false($firstParam->isOptional());
        Assert::false($firstParam->isDefaultValueAvailable());
        Assert::false($firstParam->allowsNull());

        Assert::true($reflection->hasMethod(name: 'visitRaw'));
        $visitRawMethod = $reflection->getMethod(name: 'visitRaw');
        Assert::null($visitRawMethod->getReturnType());
        $methodDocComment = $visitRawMethod->getDocComment();
        Assert::true(is_string($methodDocComment));
        Assert::string($methodDocComment)->contains('@return T');
        $parameters = $visitRawMethod->getParameters();
        Assert::count($parameters, 1);
        $firstParam = $parameters[0];
        Assert::same($firstParam->getName(), 'specification');
        $paramType = $firstParam->getType();
        Assert::instanceOf($paramType, \ReflectionNamedType::class);
        Assert::same($paramType->getName(), RawSpecification::class);
        Assert::false($firstParam->isOptional());
        Assert::false($firstParam->isDefaultValueAvailable());
        Assert::false($firstParam->allowsNull());

        $methodNames = array_map(
            callback: static fn(ReflectionMethod $method): string => $method->getName(),
            array: $methods,
        );

        $expectedMethods = [
            'visitComparison',
            'visitComposite',
            'visitNot',
            'visitOr',
            'visitOrCondition',
            'visitRaw',
            'visitOrderBy',
            'visitLimit',
            'visitOffset',
        ];

        foreach ($expectedMethods as $expectedMethod) {
            Assert::contains($methodNames, $expectedMethod);
        }
    }
}
