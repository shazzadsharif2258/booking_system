<?php
session_start();
require_once '../vendor/autoload.php';
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(403);
    echo 'Unauthorized access';
    exit;
}

$customerId = $_SESSION['user_id'];
$stripeSecretKey = 'sk_test_51RM8NP2MRj4ABXCGDeOUhgxYTS0wDJrGEPJmogpTBqLuYkvNpgyvkEhBw4Rz20fu1AcnCxJs0CKurL3MI9w8HMGn00ZgkfB2HA';
if (!$stripeSecretKey) {
    die("Stripe secret key not set.");
}

\Stripe\Stripe::setApiKey($stripeSecretKey);

// Get item details from POST or GET
$itemType = $_POST['item_type'] ?? $_GET['item_type'] ?? null;
$foodId = $_POST['food_id'] ?? $_GET['food_id'] ?? null;
$eventId = $_POST['event_id'] ?? $_GET['event_id'] ?? null;
$chefId = $_POST['chef_id'] ?? $_GET['chef_id'] ?? null;
$vendorId = $_POST['vendor_id'] ?? $_GET['vendor_id'] ?? null;
$foodName = $_POST['food_name'] ?? $_GET['food_name'] ?? '';
$eventName = $_POST['event_name'] ?? $_GET['event_name'] ?? '';
$price = (float)($_POST['price'] ?? $_GET['price'] ?? 0.0);
$quantity = (int)($_POST['quantity'] ?? $_GET['quantity'] ?? 1);
$phone = $_POST['phone'] ?? '';

// Validate item inputs
if (!$itemType || ($itemType === 'food' && !$foodId) || ($itemType === 'event' && !$eventId) || $quantity <= 0) {
    $_SESSION['payment_error'] = 'Invalid item or quantity.';
    header('Location: index.php?error=' . urlencode('Invalid item or quantity.'));
    exit;
}

// Fetch item details from database if price not provided
if ($price <= 0) {
    if ($itemType === 'food') {
        $itemQuery = "SELECT name, price FROM food_items WHERE id = ?";
        $item = $db->selectOne($itemQuery, [$foodId]);
        if (!$item) {
            $_SESSION['payment_error'] = 'Food item not found.';
            header('Location: index.php?error=' . urlencode('Food item not found.'));
            exit;
        }
        $price = $item['price'];
        $itemName = $item['name'];
    } elseif ($itemType === 'event') {
        $itemQuery = "SELECT name, base_cost AS price FROM events WHERE id = ?";
        $item = $db->selectOne($itemQuery, [$eventId]);
        if (!$item) {
            $_SESSION['payment_error'] = 'Event not found.';
            header('Location: index.php?error=' . urlencode('Event not found.'));
            exit;
        }
        $price = $item['price'];
        $itemName = $item['name'];
    }
} else {
    $itemName = $itemType === 'event' ? $eventName : $foodName;
    if (!$itemName) {
        $itemQuery = $itemType === 'event' ? "SELECT name FROM events WHERE id = ?" : "SELECT name FROM food_items WHERE id = ?";
        $item = $db->selectOne($itemQuery, [$itemType === 'event' ? $eventId : $foodId]);
        $itemName = $item['name'] ?? ($itemType === 'event' ? 'Event' : 'Food Item');
    }
}

// Function to prepare single item for Stripe Checkout
function prepareSingleItemForCheckout($itemName, $price, $quantity): array {
    return [
        [
            "quantity" => $quantity,
            "price_data" => [
                "currency" => "bdt",
                "unit_amount" => $price * 100,
                "product_data" => [
                    "name" => $itemName
                ]
            ]
        ]
    ];
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    $paymentMethod = $_POST['payment_method'];
    $transactionId = $_POST['transaction_id'] ?? '';
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');

    if (empty($deliveryAddress)) {
        $_SESSION['payment_error'] = 'Delivery address is required.';
        header('Location: make_payment.php?error=' . urlencode('Delivery address is required.'));
        exit;
    }

    if ($paymentMethod === 'stripe') {
        try {
            $checkoutSession = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => prepareSingleItemForCheckout($itemName, $price, $quantity),
                'mode' => 'payment',
                'success_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/customer/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/customer/make_payment.php?error=' . urlencode('Payment cancelled.'),
                'metadata' => [
                    'customer_id' => $customerId,
                    'delivery_address' => $deliveryAddress,
                    'item_type' => $itemType,
                    'food_id' => $foodId,
                    'event_id' => $eventId,
                    'chef_id' => $chefId,
                    'vendor_id' => $vendorId,
                    'quantity' => $quantity,
                    'price' => $price
                ]
            ]);

            header('Location: ' . $checkoutSession->url);
            exit;
        } catch (Exception $e) {
            $_SESSION['payment_error'] = 'Error creating Stripe session: ' . $e->getMessage();
            header('Location: make_payment.php?error=' . urlencode('Error creating Stripe session: ' . $e->getMessage()));
            exit;
        }
    } else {
        header('Location: confirm_order.php');
        exit;
    }
}

$errorMessage = $_SESSION['payment_error'] ?? '';
unset($_SESSION['payment_error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - Homemade Food Delivery</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.5)), 
                        url('https://images.unsplash.com/photo-1504674900247-087ca5f5c2f0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80') no-repeat center center fixed;
            background-size: cover;
            color: #333;
            line-height: 1.6;
            padding: 40px 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        h2 {
            font-size: 2.5em;
            color: #2e7d32;
            margin-bottom: 30px;
            font-weight: 600;
            text-align: center;
        }

        .payment-container {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            animation: slideUp 0.8s ease-in-out;
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        label {
            display: block;
            font-size: 1em;
            color: #333;
            margin-bottom: 8px;
            font-weight: 500;
        }

        select,
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            margin-bottom: 15px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        select:focus,
        input[type="text"]:focus {
            border-color: #2e7d32;
            box-shadow: 0 0 5px rgba(46, 125, 50, 0.3);
            outline: none;
        }

        button {
            background: linear-gradient(45deg, #2e7d32, #66bb6a);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 1.1em;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
        }

        button:hover {
            background: linear-gradient(45deg, #66bb6a, #2e7d32);
            transform: translateY(-2px);
        }

        .error {
            color: #d32f2f;
            font-size: 1em;
            margin-bottom: 15px;
            display: none;
        }

        .error.visible {
            display: block;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 480px) {
            body {
                padding: 20px 15px;
            }

            .payment-container {
                padding: 20px;
            }

            h2 {
                font-size: 2em;
            }

            button {
                padding: 10px 20px;
                font-size: 1em;
            }
        }
    </style>
</head>
<body>
    <h2>Make Payment</h2>

    <div class="payment-container">
        <form id="payment-form" method="POST">
            <input type="hidden" name="item_type" value="<?php echo htmlspecialchars($itemType); ?>">
            <input type="hidden" name="food_id" value="<?php echo htmlspecialchars($foodId ?? ''); ?>">
            <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($eventId ?? ''); ?>">
            <input type="hidden" name="chef_id" value="<?php echo htmlspecialchars($chefId ?? ''); ?>">
            <input type="hidden" name="vendor_id" value="<?php echo htmlspecialchars($vendorId ?? ''); ?>">
            <input type="hidden" name="food_name" value="<?php echo htmlspecialchars($foodName ?? ''); ?>">
            <input type="hidden" name="event_name" value="<?php echo htmlspecialchars($eventName ?? ''); ?>">
            <input type="hidden" name="price" value="<?php echo htmlspecialchars($price); ?>">
            <input type="hidden" name="quantity" value="<?php echo htmlspecialchars($quantity); ?>">
            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
            
            <label>Select Payment Method:</label>
            <select name="payment_method" required>
                <option value="stripe">Stripe</option>
                <option value="bkash">Bkash</option>
                <option value="nagad">Nagad</option>
                <option value="rocket">Rocket</option>
            </select>
            
            <label>Delivery Address:</label>
            <input type="text" name="delivery_address" value="<?php echo htmlspecialchars($_SESSION['user_address'] ?? 'Matuail'); ?>" required>

            <label>Transaction ID (for non-Stripe methods):</label>
            <input type="text" name="transaction_id" class="hidden" required>

            <div id="payment-errors" class="error <?php echo $errorMessage ? 'visible' : ''; ?>">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>

            <button type="submit">Pay</button>
        </form>
    </div>

    <script>
        const form = document.getElementById('payment-form');
        const transactionInput = document.querySelector('input[name="transaction_id"]');
        const paymentErrors = document.getElementById('payment-errors');

        form.querySelector('select[name="payment_method"]').addEventListener('change', (e) => {
            const isStripe = e.target.value === 'stripe';
            transactionInput.classList.toggle('hidden', isStripe);
            if (isStripe) {
                transactionInput.removeAttribute('required');
            } else {
                transactionInput.setAttribute('required', '');
            }
        });

        form.addEventListener('submit', (event) => {
            const paymentMethod = form.querySelector('select[name="payment_method"]').value;
            if (paymentMethod !== 'stripe' && !transactionInput.value) {
                event.preventDefault();
                paymentErrors.textContent = 'Please enter a Transaction ID for non-Stripe payment methods.';
                paymentErrors.classList.add('visible');
            }
        });

        if (form.querySelector('select[name="payment_method"]').value !== 'stripe') {
            transactionInput.classList.remove('hidden');
        } else {
            transactionInput.classList.add('hidden');
            transactionInput.removeAttribute('required');
        }
    </script>
</body>
</html>