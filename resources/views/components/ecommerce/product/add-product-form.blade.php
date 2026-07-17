<?php
// Define variables and set to empty values
$productName = $category = $brand = $color = $weight = $length = $width = "";
$description = $pricingWeight = $pricingLength = $pricingWidth = "";
$stockQuantity = 1;
$availability = "";
$image = "";
$action = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $productName = test_input($_POST["product_name"]);
    $category = test_input($_POST["category"]);
    $brand = test_input($_POST["brand"]);
    $color = test_input($_POST["color"]);
    $weight = test_input($_POST["weight"]);
    $length = test_input($_POST["length"]);
    $width = test_input($_POST["width"]);
    $description = test_input($_POST["description"]);
    $pricingWeight = test_input($_POST["pricing_weight"]);
    $pricingLength = test_input($_POST["pricing_length"]);
    $pricingWidth = test_input($_POST["pricing_width"]);
    $stockQuantity = test_input($_POST["stock_quantity"]);
    $availability = test_input($_POST["availability"]);
    $action = test_input($_POST["action"]);

    // Handle file upload
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $targetDir = "uploads/";
        $targetFile = $targetDir . basename($_FILES["image"]["name"]);
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        // Check if image file is actual image
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check !== false) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
                $image = $targetFile;
            }
        }
    }
}

function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>

<form method="POST" action="{{ route('add-product') }}" enctype="multipart/form-data">
    @csrf
    <div class="space-y-6">
        <!-- Products Description Section -->
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h2 class="text-lg font-medium text-gray-800 dark:text-white">Products Description</h2>
            </div>
            <div class="p-4 sm:p-6 dark:border-gray-800">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="product-name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Product Name
                        </label>
                        <input type="text" name="product_name" id="product-name" value="<?php echo $productName; ?>"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            placeholder="Enter product name" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Category
                        </label>
                        <div class="relative z-20 bg-transparent">
                            <select name="category"
                                class="h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                <option value="">Select Category</option>
                                <option value="Laptop" <?php echo ($category == "Laptop") ? "selected" : ""; ?>>Laptop</option>
                                <option value="Phone" <?php echo ($category == "Phone") ? "selected" : ""; ?>>Phone</option>
                                <option value="Watch" <?php echo ($category == "Watch") ? "selected" : ""; ?>>Watch</option>
                                <option value="Electronics" <?php echo ($category == "Electronics") ? "selected" : ""; ?>>Electronics</option>
                                <option value="Accessories" <?php echo ($category == "Accessories") ? "selected" : ""; ?>>Accessories</option>
                            </select>
                            <span class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-700 dark:text-gray-400">
                                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Brand</label>
                        <div class="relative z-20 bg-transparent">
                            <select name="brand"
                                class="h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                <option value="">Select Brand</option>
                                <option value="Apple" <?php echo ($brand == "Apple") ? "selected" : ""; ?>>Apple</option>
                                <option value="Samsung" <?php echo ($brand == "Samsung") ? "selected" : ""; ?>>Samsung</option>
                                <option value="LG" <?php echo ($brand == "LG") ? "selected" : ""; ?>>LG</option>
                            </select>
                            <span class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-700 dark:text-gray-400">
                                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Color</label>
                        <div class="relative z-20 bg-transparent">
                            <select name="color"
                                class="h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                <option value="">Select color</option>
                                <option value="Black" <?php echo ($color == "Black") ? "selected" : ""; ?>>Black</option>
                                <option value="White" <?php echo ($color == "White") ? "selected" : ""; ?>>White</option>
                                <option value="Silver" <?php echo ($color == "Silver") ? "selected" : ""; ?>>Silver</option>
                                <option value="Blue" <?php echo ($color == "Blue") ? "selected" : ""; ?>>Blue</option>
                                <option value="Gold" <?php echo ($color == "Gold") ? "selected" : ""; ?>>Gold</option>
                            </select>
                            <span class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-700 dark:text-gray-400">
                                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div class="col-span-full">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                            <div>
                                <label for="weight" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Weight (kg)
                                </label>
                                <input type="number" name="weight" id="weight" value="<?php echo $weight; ?>" step="0.01"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="15" />
                            </div>
                            <div>
                                <label for="length" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Length (cm)
                                </label>
                                <input type="number" name="length" id="length" value="<?php echo $length; ?>" step="0.01"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="120" />
                            </div>
                            <div>
                                <label for="width" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Width (cm)
                                </label>
                                <input type="number" name="width" id="width" value="<?php echo $width; ?>" step="0.01"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="23" />
                            </div>
                        </div>
                    </div>

                    <div class="col-span-full">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
                        <textarea name="description" placeholder="Receipt Info (optional)" rows="7"
                            class="w-full resize-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"><?php echo $description; ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing & Availability Section -->
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h2 class="text-lg font-medium text-gray-800 dark:text-white">Pricing & Availability</h2>
            </div>
            <div class="space-y-5 p-4 sm:p-6">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <div>
                        <label for="pricing-weight" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Weight (kg)
                        </label>
                        <input type="number" name="pricing_weight" id="pricing-weight" value="<?php echo $pricingWeight; ?>" step="0.01"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            placeholder="15" />
                    </div>
                    <div>
                        <label for="pricing-length" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Length (cm)
                        </label>
                        <input type="number" name="pricing_length" id="pricing-length" value="<?php echo $pricingLength; ?>" step="0.01"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            placeholder="120" />
                    </div>
                    <div>
                        <label for="pricing-width" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Width (cm)
                        </label>
                        <input type="number" name="pricing_width" id="pricing-width" value="<?php echo $pricingWidth; ?>" step="0.01"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            placeholder="23" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="stock-quantity" class="mb-1 inline-block text-sm font-semibold text-gray-700 dark:text-gray-400">
                            Stock Quantity
                        </label>
                        <input type="number" name="stock_quantity" id="stock-quantity" value="<?php echo $stockQuantity; ?>" min="0"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            placeholder="1" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Availability Status
                        </label>
                        <div class="relative z-20 bg-transparent">
                            <select name="availability"
                                class="h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                <option value="">Select Availability</option>
                                <option value="In Stock" <?php echo ($availability == "In Stock") ? "selected" : ""; ?>>In Stock</option>
                                <option value="Out of Stock" <?php echo ($availability == "Out of Stock") ? "selected" : ""; ?>>Out of Stock</option>
                            </select>
                            <span class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-700 dark:text-gray-400">
                                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Images Section -->
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h2 class="text-lg font-medium text-gray-800 dark:text-white">Products Images</h2>
            </div>
            <div class="p-4 sm:p-6">
                <label for="product-image"
                    class="block cursor-pointer rounded-lg border-2 border-dashed border-gray-300 transition hover:border-blue-500 dark:border-gray-800">
                    <div class="flex justify-center p-10">
                        <div class="flex max-w-[260px] flex-col items-center gap-4">
                            <div class="inline-flex h-13 w-13 items-center justify-center rounded-full border border-gray-200 text-gray-700 transition dark:border-gray-800 dark:text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M20.0004 16V18.5C20.0004 19.3284 19.3288 20 18.5004 20H5.49951C4.67108 20 3.99951 19.3284 3.99951 18.5V16M12.0015 4L12.0015 16M7.37454 8.6246L11.9994 4.00269L16.6245 8.6246"
                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                                <span class="font-medium text-gray-800 dark:text-white/90">Click to upload</span> or drag and drop SVG, PNG, JPG or GIF (MAX. 800x400px)
                            </p>
                        </div>
                    </div>
                    <input type="file" name="image" id="product-image" class="hidden" accept="image/*" />
                </label>
            </div>
        </div>

        <!-- Buttons -->
        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
            <button type="submit" name="action" value="draft"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-3 text-sm font-medium text-gray-700 ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]">
                Draft
            </button>
            <button type="submit" name="action" value="publish"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-500 px-4 py-3 text-sm font-medium text-white transition hover:bg-blue-600">
                Publish Product
            </button>
        </div>
    </div>
</form>

{{-- <?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo '<div class="mt-8 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">';
    echo '<h2 class="mb-4 text-lg font-medium text-gray-800 dark:text-white">Submitted Product Information:</h2>';
    echo '<div class="space-y-2 text-sm text-gray-700 dark:text-gray-400">';
    echo '<p><strong>Product Name:</strong> ' . $productName . '</p>';
    echo '<p><strong>Category:</strong> ' . $category . '</p>';
    echo '<p><strong>Brand:</strong> ' . $brand . '</p>';
    echo '<p><strong>Color:</strong> ' . $color . '</p>';
    echo '<p><strong>Weight:</strong> ' . $weight . ' kg</p>';
    echo '<p><strong>Length:</strong> ' . $length . ' cm</p>';
    echo '<p><strong>Width:</strong> ' . $width . ' cm</p>';
    echo '<p><strong>Description:</strong> ' . $description . '</p>';
    echo '<p><strong>Pricing Weight:</strong> ' . $pricingWeight . ' kg</p>';
    echo '<p><strong>Pricing Length:</strong> ' . $pricingLength . ' cm</p>';
    echo '<p><strong>Pricing Width:</strong> ' . $pricingWidth . ' cm</p>';
    echo '<p><strong>Stock Quantity:</strong> ' . $stockQuantity . '</p>';
    echo '<p><strong>Availability:</strong> ' . $availability . '</p>';
    echo '<p><strong>Action:</strong> ' . $action . '</p>';
    if ($image) {
        echo '<p><strong>Image:</strong> ' . $image . '</p>';
    }
    echo '</div>';
    echo '</div>';
}
?> --}}
