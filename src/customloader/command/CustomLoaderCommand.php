<?php

declare(strict_types=1);

namespace customloader\command;

use customloader\CustomLoader;
use customloader\manager\CustomBlockManager;
use customloader\manager\CustomEntityManager;
use customloader\manager\CustomItemManager;
use customloader\pack\ResourcePackBuilder;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use Symfony\Component\Filesystem\Path;
use function array_shift;
use function array_values;
use function trim;

class CustomLoaderCommand extends Command implements PluginOwned{
	use PluginOwnedTrait;

	public function __construct(){
		parent::__construct("customloader", "CustomLoader management", "/cl <create|build|additem|reload>", ["cl"]);
		$this->setPermission("customloader.command");
		$this->owningPlugin = CustomLoader::getInstance();
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return false;
		}

		$subCommand = array_shift($args);
		switch($subCommand ?? ""){
			case "create":
				return $this->handleCreate($sender, $args);
			case "build":
				return $this->handleBuild($sender, $args);
			case "additem":
				return $this->handleAddItem($sender, $args);
			case "reload":
				return $this->handleReload($sender);
			default:
				$sender->sendMessage("§eCustomLoader Commands:");
				$sender->sendMessage("§f/cl create <packName> [description] §7- Create a new resource/behavior pack");
				$sender->sendMessage("§f/cl build <packName> §7- Build .mcpack files from the pack folder");
				$sender->sendMessage("§f/cl additem <packName> <itemName> <namespace> §7- Add an item entry manually");
				$sender->sendMessage("§f/cl reload §7- Reload config (restart needed for full effect)");
				return true;
		}
	}

	private function handleCreate(CommandSender $sender, array $args) : bool{
		$packName = array_shift($args);
		if(trim($packName ?? "") === ""){
			$sender->sendMessage("§cUsage: /cl create <packName> [description]");
			return false;
		}
		$description = trim(implode(" ", $args) ?: "CustomLoader resource pack");

		$plugin = CustomLoader::getInstance();
		$builder = new ResourcePackBuilder($plugin->getDataFolder(), $packName);

		if($builder->resourcePackExists()){
			$sender->sendMessage("§cPack \"$packName\" already exists.");
			return false;
		}

		// Collect all registered items/blocks/entities
		$items = [];
		foreach(CustomItemManager::getInstance()->getItems() as $item){
			/** @phpstan-ignore-next-line */
			if(method_exists($item, 'getProperties')){
				/** @phpstan-ignore-next-line */
				$items[] = $item->getProperties();
			}
		}
		$blocks = array_values(array_map(
			static fn($b) => $b->getProperties(),
			CustomBlockManager::getInstance()->getBlocks()
		));
		$entities = array_values(CustomEntityManager::getInstance()->getRegisteredEntities());

		$builder->create($description, $items, $blocks, $entities);
		$sender->sendMessage("§aPack \"$packName\" created successfully!");
		$sender->sendMessage("§7Resource pack: " . $builder->getRpDir());
		$sender->sendMessage("§7Behavior pack: " . $builder->getBpDir());
		$sender->sendMessage("§eAdd your texture PNGs to the textures/ folders, then run §f/cl build {$packName}");
		return true;
	}

	private function handleBuild(CommandSender $sender, array $args) : bool{
		$packName = array_shift($args);
		if(trim($packName ?? "") === ""){
			$sender->sendMessage("§cUsage: /cl build <packName>");
			return false;
		}

		$builder = new ResourcePackBuilder(CustomLoader::getInstance()->getDataFolder(), $packName);
		if(!$builder->resourcePackExists()){
			$sender->sendMessage("§cPack \"$packName\" not found. Run /cl create first.");
			return false;
		}

		$paths = $builder->buildMcpacks();
		$sender->sendMessage("§aBuild complete!");
		foreach($paths as $path){
			$sender->sendMessage("§7" . $path);
		}
		return true;
	}

	private function handleAddItem(CommandSender $sender, array $args) : bool{
		$packName = array_shift($args);
		$itemName = array_shift($args);
		$namespace = array_shift($args);

		if(trim($packName ?? "") === "" || trim($itemName ?? "") === "" || trim($namespace ?? "") === ""){
			$sender->sendMessage("§cUsage: /cl additem <packName> <itemName> <namespace>");
			return false;
		}

		$builder = new ResourcePackBuilder(CustomLoader::getInstance()->getDataFolder(), $packName);
		if(!$builder->resourcePackExists()){
			$sender->sendMessage("§cPack \"$packName\" not found.");
			return false;
		}

		$builder->addItemEntry($itemName, $namespace, $itemName);
		$sender->sendMessage("§aAdded item §f{$itemName} §a(namespace: §f{$namespace}§a) to pack.");
		$sender->sendMessage("§eDon't forget to add the texture PNG and update config.yml!");
		return true;
	}

	private function handleReload(CommandSender $sender) : bool{
		CustomLoader::getInstance()->reloadConfig();
		$sender->sendMessage("§aConfig reloaded. §eRestart the server to apply entity/block/item changes.");
		return true;
	}
}
