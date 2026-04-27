<?php

namespace Go\ParserReflection\Stub {

    class TestClassWithNewExpressionDefaults
    {
        /**
         * Method with DateTimeImmutable default value as in the reported issue
         */
        public function deactivateSeries(\DateTimeImmutable $today = new \DateTimeImmutable('today')): bool
        {
            return true;
        }

        /**
         * Method with DateTime default value
         */
        public function withDateTime(\DateTime $date = new \DateTime('2023-01-01')): void
        {
        }

        /**
         * Method with stdClass default value (no constructor args)
         */
        public function withStdClass(\stdClass $obj = new \stdClass()): void
        {
        }
    }
}
