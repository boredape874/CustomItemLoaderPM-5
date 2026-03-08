<?php

declare(strict_types=1);

namespace customloader\recipe;

use customloader\CustomLoader;
use pocketmine\crafting\CraftingManager;
use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\crafting\FurnaceRecipe;
use pocketmine\crafting\FurnaceType;
use pocketmine\crafting\RecipeIngredient;
use pocketmine\crafting\ShapedRecipe;
use pocketmine\crafting\ShapelessRecipe;
use pocketmine\crafting\ShapelessRecipeType;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\utils\SingletonTrait;
use InvalidArgumentException;
use Throwable;
use function array_is_list;
use function array_map;
use function count;
use function is_array;
use function is_string;
use function sprintf;
use function strtolower;

/**
 * Singleton manager that parses and registers custom recipes from plugin config.
 *
 * Supported recipe types:
 *   shaped        — crafting table, shape-based
 *   shapeless     — crafting table, ingredient-list-based
 *   furnace       — standard furnace
 *   blast_furnace — blast furnace
 *   smoker        — smoker
 *   campfire      — campfire
 *   soul_campfire — soul campfire
 *   stonecutter   — stonecutter (ShapelessRecipe with STONECUTTER type)
 *
 * Config shape:
 *   recipes:
 *     ruby_sword:
 *       type: shaped
 *       pattern: ["R R", " S ", " S "]
 *       ingredients:
 *         R: "example:ruby_gem"
 *         S: "minecraft:stick"
 *       result: {id: "example:ruby_sword", count: 1}
 *
 *     coal_to_ruby:
 *       type: furnace           # also: blast_furnace / smoker / campfire / soul_campfire
 *       input: "minecraft:coal"
 *       result: {id: "example:ruby_gem", count: 1}
 *
 *     ruby_ring:
 *       type: shapeless
 *       ingredients: ["example:ruby_gem", "minecraft:gold_ingot"]
 *       result: {id: "example:ruby_ring", count: 1}
 *
 *     ruby_shard:
 *       type: stonecutter
 *       input: "example:ruby_gem"
 *       result: {id: "example:ruby_shard", count: 2}
 */
final class CustomRecipeManager{
	use SingletonTrait;

	private int $recipeCount = 0;

	public function __construct(){}

	/**
	 * Parses the "recipes" config section and registers every valid recipe.
	 * Errors for individual recipes are logged and skipped; they do not stop others.
	 *
	 * @param array<string, mixed> $data           The raw "recipes" array from config.
	 * @param CraftingManager      $craftingManager The server crafting manager.
	 */
	public function registerDefaultRecipes(array $data, CraftingManager $craftingManager) : void{
		foreach($data as $name => $recipeData){
			$name = (string) $name;
			if(!is_array($recipeData)){
				CustomLoader::getInstance()->getLogger()->warning(
					"Recipe '$name': expected a mapping, got a scalar — skipping."
				);
				continue;
			}
			try{
				$this->registerRecipe($name, $recipeData, $craftingManager);
				$this->recipeCount++;
			}catch(Throwable $e){
				CustomLoader::getInstance()->getLogger()->warning(
					sprintf("Failed to load recipe '%s': %s", $name, $e->getMessage())
				);
			}
		}
	}

	/**
	 * Registers one recipe by its config data.
	 *
	 * @throws InvalidArgumentException on any bad config value.
	 */
	private function registerRecipe(string $name, array $data, CraftingManager $craftingManager) : void{
		$type = strtolower((string) ($data["type"] ?? ""));

		match($type){
			"shaped"       => $this->registerShaped($name, $data, $craftingManager),
			"shapeless"    => $this->registerShapeless($name, $data, $craftingManager),
			"furnace"      => $this->registerFurnace($name, $data, $craftingManager, FurnaceType::FURNACE),
			"blast_furnace"=> $this->registerFurnace($name, $data, $craftingManager, FurnaceType::BLAST_FURNACE),
			"smoker"       => $this->registerFurnace($name, $data, $craftingManager, FurnaceType::SMOKER),
			"campfire"     => $this->registerFurnace($name, $data, $craftingManager, FurnaceType::CAMPFIRE),
			"soul_campfire"=> $this->registerFurnace($name, $data, $craftingManager, FurnaceType::SOUL_CAMPFIRE),
			"stonecutter"  => $this->registerStonecutter($name, $data, $craftingManager),
			default        => throw new InvalidArgumentException(
				"Unknown recipe type '$type'. Valid types: shaped, shapeless, furnace, blast_furnace, smoker, campfire, soul_campfire, stonecutter."
			),
		};
	}

	// -----------------------------------------------------------------------
	// Shaped recipe
	// -----------------------------------------------------------------------

	private function registerShaped(string $name, array $data, CraftingManager $craftingManager) : void{
		$pattern = $data["pattern"] ?? null;
		if(!is_array($pattern) || !array_is_list($pattern) || count($pattern) === 0){
			throw new InvalidArgumentException("'pattern' must be a non-empty list of strings.");
		}
		$pattern = array_map(static fn(mixed $row) => (string) $row, $pattern);

		$rawIngredients = $data["ingredients"] ?? null;
		if(!is_array($rawIngredients) || array_is_list($rawIngredients)){
			throw new InvalidArgumentException("'ingredients' must be a key=>itemId mapping.");
		}

		/** @var array<string, RecipeIngredient> $ingredients */
		$ingredients = [];
		foreach($rawIngredients as $key => $itemId){
			$key = (string) $key;
			if(!is_string($itemId)){
				throw new InvalidArgumentException("Ingredient key '$key' must map to a string item ID.");
			}
			$ingredients[$key] = new ExactRecipeIngredient($this->parseItem($itemId, "'$key' ingredient"));
		}

		$result = $this->parseResultItem($data["result"] ?? null, $name);

		$craftingManager->registerShapedRecipe(new ShapedRecipe($pattern, $ingredients, [$result]));
	}

	// -----------------------------------------------------------------------
	// Shapeless recipe
	// -----------------------------------------------------------------------

	private function registerShapeless(string $name, array $data, CraftingManager $craftingManager) : void{
		$rawIngredients = $data["ingredients"] ?? null;
		if(!is_array($rawIngredients) || !array_is_list($rawIngredients) || count($rawIngredients) === 0){
			throw new InvalidArgumentException("'ingredients' must be a non-empty list of item IDs.");
		}

		/** @var RecipeIngredient[] $ingredients */
		$ingredients = [];
		foreach($rawIngredients as $idx => $itemId){
			if(!is_string($itemId)){
				throw new InvalidArgumentException("Ingredient at index $idx must be a string item ID.");
			}
			$ingredients[] = new ExactRecipeIngredient($this->parseItem($itemId, "ingredient[$idx]"));
		}

		$result = $this->parseResultItem($data["result"] ?? null, $name);

		$craftingManager->registerShapelessRecipe(
			new ShapelessRecipe($ingredients, [$result], ShapelessRecipeType::CRAFTING)
		);
	}

	// -----------------------------------------------------------------------
	// Furnace-family recipes (furnace / blast_furnace / smoker / campfire / soul_campfire)
	// -----------------------------------------------------------------------

	private function registerFurnace(
		string $name,
		array $data,
		CraftingManager $craftingManager,
		FurnaceType $furnaceType
	) : void{
		$inputId = $data["input"] ?? null;
		if(!is_string($inputId) || $inputId === ""){
			throw new InvalidArgumentException("'input' must be a non-empty string item ID.");
		}

		$ingredient = new ExactRecipeIngredient($this->parseItem($inputId, "'input'"));
		$result     = $this->parseResultItem($data["result"] ?? null, $name);

		$craftingManager->getFurnaceRecipeManager($furnaceType)->register(
			new FurnaceRecipe($result, $ingredient)
		);
	}

	// -----------------------------------------------------------------------
	// Stonecutter recipe (shapeless with STONECUTTER type, single input)
	// -----------------------------------------------------------------------

	private function registerStonecutter(string $name, array $data, CraftingManager $craftingManager) : void{
		$inputId = $data["input"] ?? null;
		if(!is_string($inputId) || $inputId === ""){
			throw new InvalidArgumentException("'input' must be a non-empty string item ID.");
		}

		$ingredient = new ExactRecipeIngredient($this->parseItem($inputId, "'input'"));
		$result     = $this->parseResultItem($data["result"] ?? null, $name);

		$craftingManager->registerShapelessRecipe(
			new ShapelessRecipe([$ingredient], [$result], ShapelessRecipeType::STONECUTTER)
		);
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Parses a string item ID into an Item, throwing on failure.
	 *
	 * @throws InvalidArgumentException when the item ID cannot be resolved.
	 */
	private function parseItem(string $id, string $context) : Item{
		$item = StringToItemParser::getInstance()->parse($id);
		if($item === null){
			throw new InvalidArgumentException("Unknown item ID '$id' for $context.");
		}
		return $item;
	}

	/**
	 * Parses a result entry: {id: "namespace:id", count: N}
	 *
	 * @param mixed  $resultData Raw config value for "result".
	 * @throws InvalidArgumentException on any bad value.
	 */
	private function parseResultItem(mixed $resultData, string $recipeName) : Item{
		if(!is_array($resultData) || !isset($resultData["id"])){
			throw new InvalidArgumentException(
				"Recipe '$recipeName': 'result' must be a mapping with at least an 'id' key."
			);
		}

		$item  = $this->parseItem((string) $resultData["id"], "'result.id'");
		$count = max(1, (int) ($resultData["count"] ?? 1));
		$item->setCount($count);
		return $item;
	}

	public function getRecipeCount() : int{
		return $this->recipeCount;
	}
}
