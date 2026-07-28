<?php

/**
 * Standalone unit-test bootstrap.
 *
 * The unit tests depend on a handful of Magento contracts, which they mock or use
 * as plain data carriers. This bootstrap autoloads the module's own classes and
 * defines minimal stand-ins for those contracts *only when the real framework is
 * absent*, so the tests run in plain CI (no Magento install). Inside a real Magento
 * install the genuine classes exist and win.
 */

namespace {
    spl_autoload_register(static function ($class) {
        $prefix = 'Softcode\\CheckoutOverride\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $path = __DIR__ . '/../' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });

    if (!function_exists('__')) {
        function __($text, ...$args)
        {
            return $text;
        }
    }
}

namespace Magento\Framework {
    if (!class_exists(DataObject::class)) {
        class DataObject
        {
            protected $data = [];

            public function __construct(array $data = [])
            {
                $this->data = $data;
            }

            public function getData($key = '', $index = null)
            {
                if ($key === '') {
                    return $this->data;
                }
                return $this->data[$key] ?? null;
            }

            public function setData($key, $value = null)
            {
                if (is_array($key)) {
                    $this->data = $key;
                } else {
                    $this->data[$key] = $value;
                }
                return $this;
            }
        }
    }

    if (!class_exists(Event::class)) {
        class Event extends DataObject
        {
            public function getQuote()
            {
                return $this->getData('quote');
            }

            public function getOrder()
            {
                return $this->getData('order');
            }
        }
    }
}

namespace Magento\Framework\Event {
    if (!class_exists(Observer::class)) {
        class Observer extends \Magento\Framework\DataObject
        {
            public function getEvent()
            {
                return $this->getData('event');
            }
        }
    }

    if (!interface_exists(ObserverInterface::class)) {
        interface ObserverInterface
        {
            public function execute(Observer $observer);
        }
    }
}

namespace Magento\Framework\Exception {
    if (!class_exists(LocalizedException::class)) {
        class LocalizedException extends \Exception
        {
            public function __construct($phrase = '', ?\Exception $cause = null, $code = 0)
            {
                parent::__construct((string) $phrase, (int) $code, $cause);
            }
        }
    }
}

namespace Magento\Quote\Model {
    if (!class_exists(Quote::class)) {
        class Quote
        {
            public function getData($key = '', $index = null)
            {
            }

            public function getPayment()
            {
            }
        }
    }
}

namespace Magento\Quote\Model\Quote {
    if (!class_exists(Payment::class)) {
        class Payment
        {
            public function getMethod()
            {
            }
        }
    }
}
