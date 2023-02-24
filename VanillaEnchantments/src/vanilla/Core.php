<?php
namespace vanilla;

use pocketmine\Player;

use pocketmine\plugin\PluginBase;

use pocketmine\block\Block;

use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\item\Sword;
use pocketmine\item\ItemFactory;

use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\VanillaEnchantments;

use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\data\bedrock\EnchantmentIdMap;

use pocketmine\data\bedrock\EnchantmentIds;

use pocketmine\event\Listener;

use pocketmine\event\block\BlockBreakEvent;

use pocketmine\event\entity\EntityDamageByEntityEvent;

use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\block\BlockLegacyIds;
use vanilla\item\EnchantedBook;
use pocketmine\data\bedrock\EntityLegacyIds;
use pocketmine\item\enchantment\ItemFlags;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\ItemIdentifier;

class Core extends PluginBase implements Listener{
	
	public const UNDEAD = [
		EntityLegacyIds::ZOMBIE,
		EntityLegacyIds::HUSK,
		EntityLegacyIds::WITHER,
		EntityLegacyIds::SKELETON,
		EntityLegacyIds::STRAY,
		EntityLegacyIds::WITHER_SKELETON,
		EntityLegacyIds::ZOMBIE_PIGMAN,
		EntityLegacyIds::ZOMBIE_VILLAGER
	];
	
	public const ARTHROPODS = [
		EntityLegacyIds::SPIDER,
		EntityLegacyIds::CAVE_SPIDER,
		EntityLegacyIds::SILVERFISH,
		EntityLegacyIds::ENDERMITE
	];
	
	public const CONFIG_VER = "1.2.5";
	
	public function onLoad(): void{
		$this->saveDefaultConfig();
			
		if($this->getConfig()->get("version", null) !== self::CONFIG_VER){
			$this->getLogger()->info("Outdated config version detected, updating config...");
			$this->saveResource("config.yml", true);
		}
			
		$this->getLogger()->info("Registering enchantments and enchanted books...");
		$enchantment = new FortuneEnchantment();
		$lt = new LootingEnchantment();
		$smite = new SmiteEnchantment();
		$boa = new BaneOfArthropodsEnchantment();
		EnchantmentIdMap::getInstance()->register($enchantment->getMcpeId(), $enchantment);
		EnchantmentIdMap::getInstance()->register($lt->getMcpeId(), $lt);
		EnchantmentIdMap::getInstance()->register($smite->getMcpeId(), $smite);
		EnchantmentIdMap::getInstance()->register($boa->getMcpeId(), $boa);
		#var_dump("SUCCESs");
			
		//ItemFactory::getInstance()->register(new EnchantedBook(new ItemIdentifier(ItemIds::ENCHANTED_BOOK, 0), "Enchant Book"), true);
			
	}
	
	public function onEnable(): void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info("Vanilla enchantments were successfully registered");
	}
	
	/**
	 * @param BlockBreakEvent $event
	 * @param ignoreCancelled true
	 * @priority LOWEST
	 */
	
	public function onBreak(BlockBreakEvent $event) : void{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$item = $event->getItem();
		$enchantment = new FortuneEnchantment();
	
		if($block->getId() == ItemIds::LEAVES){
			if(mt_rand(1, 99) <= 10){
				$event->setDrops([ItemFactory::getInstance()->get(ItemIds::APPLE, 0, 1)]);
			}
		}
				
		if(($level = $item->getEnchantmentLevel(EnchantmentIdMap::getInstance()->fromId($enchantment->getMcpeId()))) > 0){
			$add = mt_rand(0, $level + 1);
					
			if($block->getId() == BlockLegacyIds::LEAVES){
				if(mt_rand(1, 99) <= 10){
					$event->setDrops([ItemFactory::getInstance()->get(ItemIds::APPLE, 0, 1)]);
				}
			}
			
			foreach($this->getConfig()->get("fortune.blocks", []) as $str){
				$it = LegacyStringToItemParser::getInstance()->parse($str);
				
				if($block->getId() == $it->getId()){
					if(mt_rand(1, 99) <= 10 * $level){
						if(empty($event->getDrops()) == false){
							$event->setDrops(array_map(function(Item $drop) use($add){
								$drop->setCount($drop->getCount() + $add);
								return $drop;
							}, $event->getDrops()));
						}
					}
					
					break;
				}
			}
		}
	}
	
	// /**
	//  * @param EntityDamageByEntityEvent $event
	//  * @ignoreCancelled true
	//  * @priority LOWEST
	//  */
	
	public function onDamage(EntityDamageByEntityEvent $event) : void{
		$player = $event->getEntity();
		
		if(($damager = $event->getDamager()) instanceof Player){
			$item = $damager->getInventory()->getItemInHand();
				$enchantment = new SmiteEnchantment();
			if($item->hasEnchantment(EnchantmentIdMap::getInstance()->fromId($enchantment->getMcpeId()))){
				if(in_array($player::NETWORK_ID, self::UNDEAD)){
					$event->setBaseDamage($event->getBaseDamage() + (2.5 * $item->getEnchantmentLevel(EnchantmentIdMap::getInstance()->fromId($enchantment->getMcpeId()))));
				}
			}
				$ench = new BaneOfArthropodsEnchantment();
			if($item->hasEnchantment(EnchantmentIdMap::getInstance()->fromId($ench->getMcpeId()))){
				if(in_array($player::NETWORK_ID, self::ARTHROPODS)){
					$event->setBaseDamage($event->getBaseDamage() + (2.5 * $item->getEnchantmentLevel(EnchantmentIdMap::getInstance()->fromId($ench->getMcpeId()))));
				}
			}
			// $en = new LootingEnchantment();
			// if(($level = $damager->getInventory()->getItemInHand()->getEnchantmentLevel(EnchantmentIdMap::getInstance()->fromId($en->getMcpeId()))) > 0){
			// 	if($player instanceof Player == false and $player instanceof Living and $event->getFinalDamage() >= $player->getHealth()){
			// 		$add = mt_rand(0, $level + 1);
					
			// 		foreach($this->getConfig()->get("looting.entities") as $eid => $items){
			// 			$id = constant(Entity::class."::".strtoupper($eid));
						
			// 			if($player::NETWORK_ID == $id){
			// 				$drops = $this->getLootingDrops($player->getDrops(), $items, $add);
							
			// 				foreach($drops as $drop){
			// 					$damager->getLevel()->dropItem($player, $drop);
			// 				}
							
			// 				$player->flagForDespawn();
			// 			}
			// 		}
			// 	}
			// }
		}
	}
	
	// /**
	//  * @param array $drops
	//  * @param array $items
	//  * @param int $add
	//  * @return array
	//  */
	
	// public function getLootingDrops(array $drops, array $items, int $add) : array{
	// 	$r = [];
		
	// 	foreach($items as $ite){
	// 		$item = LegacyStringToItemParser::getInstance()->parse($ite);
			
	// 		foreach($drops as $drop){
	// 			if($drop->getId() == $item->getId()){
	// 				$drop->setCount($drop->getCount() + $add);
	// 			}
				
	// 			$r[] = $drop;
	// 			break;
	// 		}
	// 	}
		
	// 	return $r;
	// }
	
	// /**
	//  * @param EntityShootBowEvent $event
	//  * @ignoreCancelled true
	//  * @priority LOWEST
	//  */
	
	// public function onShoot(EntityShootBowEvent $event) : void{
	// 	$arrow = $event->getProjectile();
	// 	$bow = $event->getBow();
		
	// 	if($arrow !== null and $arrow::NETWORK_ID == Entity::ARROW){
	// 		$event->setForce($event->getForce() + 0.95); // In vanilla, arrows are fast
	// 	}
	// }
}