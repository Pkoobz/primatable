<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="bg-white shadow-lg">
    <div class="px-0">
        <div class="flex justify-between h-16">
            <div class="flex items-center ml-0 pl-4">

                <div class="flex ml-12">
                    <img src=".\assets\images\PT_Rintis.png" alt="Rintis" class="h-16 w-auto">
                </div>
                <div class="hidden sm:mr-6 sm:flex sm:space-x-8">
                    <a href="index.php" 
                       class="<?php echo $current_page === 'index' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center ml-8 px-1 pt-1 border-b-2 text-sm font-medium">
                        Dashboard
                    </a>
                </div>
            </div>
            <div class="hidden sm:mr-20 sm:flex sm:items-center">
                <div class="ml-3 relative">
                    <div class="relative inline-block text-left">
                        <button type="button" 
                                onclick="toggleDropdown()"
                                class="flex items-center max-w-xs bg-white rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" 
                                id="user-menu-button">
                            <img class="h-8 w-8 rounded-full" 
                                 src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" 
                                 alt="">
                            <span class="ml-5 text-gray-700"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        </button>
                        <div id="dropdown-menu" 
                             class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" 
                             role="menu">
                            <a href="profile.php" 
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" 
                               role="menuitem">Your Profile</a>
                            <a href="logout.php" 
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" 
                               role="menuitem">Sign out</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
function toggleDropdown() {
    const dropdown = document.getElementById('dropdown-menu');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#user-menu-button') && !e.target.closest('#dropdown-menu')) {
            if (dropdown && !dropdown.classList.contains('hidden')) {
                dropdown.classList.add('hidden');
            }
        }
    });
}
</script>
