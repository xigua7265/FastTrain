<?php
/*
     .---.    
    ( @ )~~~>___________
  .-|-|-._______________\
 _|_____|_|_|_|_|_|_|_|_|_\
| O  O  O  O  O  O  O  O | 
=]========================[=~ ~ ~

  __|            | __ __|         _)       
  _|  _` | (_-<   _|  |   _| _` |  |    \  
 _| \__,_| ___/ \__| _| _| \__,_| _| _| _| 
                                           
 * Copyright (c) 2025 xigua7265 (xigua)
 * 
 * 基于 MIT 协议授权：
 * - 允许自由使用、复制、修改、合并、发布、分发
 * - 唯一要求：保留此版权声明和协议文本
 *
 * @link https://github.com/xigua7265/FastTrain
 *
 */

namespace xigua;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\scheduler\CallbackTask;
use pocketmine\entity\Entity;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;

use xigua\Train;
use xigua\Crew;

class FastTrain extends PluginBase implements Listener {

    private $trains = [];    // 按世界存储主列车
    private $followers = []; // 按世界存储车厢
    private $lastPos = [];   // 存储所有实体最后位置
	private $minDistance = 2;   // 列车之间最小距离
	private $minTrainId = 3;   // 列车之间最小距离
	private $moveSpeed = 0.5;   // 列车移动速度
	private $tick = 2;   // tick间隔

    public function onEnable() {
        Entity::registerEntity(Train::class, true);
        Entity::registerEntity(Crew::class, true);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "update"]), $this->tick);
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
        if (!$sender instanceof Player) {
            $sender->sendMessage("控制台不可用");
            return true;
        }

        if ($cmd->getName() === "train") {
            $level = $sender->getLevel()->getName();
            if (isset($this->trains[$level])) {
                $this->addCrew($sender);
                $sender->sendMessage("§a成功加入车厢！");
            } else {
                $this->trains[$level] = $this->createTrain($sender);
                $sender->sendMessage("§a你已成为列车长！");
            }
        }
        return true;
    }

    private function createTrain(Player $p){
        $nbt = new CompoundTag("", [
            new ListTag("Pos", [
                new DoubleTag("", $p->x),
                new DoubleTag("", $p->y),
                new DoubleTag("", $p->z)
            ]),
            new ListTag("Rotation", [
                new FloatTag("", 0),
                new FloatTag("", 0)
            ]),
            new ShortTag("Health", 1),
            new StringTag("CustomName", "")
        ]);

        // 兼容旧版区块获取方式
        $chunk = $p->getLevel()->getChunk($p->x >> 4, $p->z >> 4, true);
        $train = Entity::createEntity("Train", $chunk, $nbt);
        
        if ($train instanceof Train) {
            $train->spawnToAll();
            $train->setSpeed($this->moveSpeed);
            $train->setLinked(1, $p);
            $p->setLinked(1, $train);
            return $train;
        }
        return null;
    }

	public function update() {
		foreach ($this->trains as $level => $train) {
			// 清理无效车头
			if ($train->closed || $train->getLinkedEntity() === null) {
				unset($this->trains[$level], $this->positionHistory[$train->getId()]);
				if (isset($this->followers[$level])) {
					foreach ($this->followers[$level] as $crew) {
						if (!$crew->closed) $crew->close();
						unset($this->positionHistory[$crew->getId()]);
					}
					unset($this->followers[$level]);
				}
				continue;
			}

			// 记录车头位置历史（保留$minTrainId次）
			$trainId = $train->getId();
			$currentPos = $train->getPosition();
			$currentYaw = $train->getYaw();
			$currentPitch = $train->getPitch();
			if (!isset($this->positionHistory[$trainId])) {
				$this->positionHistory[$trainId] = [];
			}
			array_push($this->positionHistory[$trainId], [
				'pos' => $currentPos,
				'yaw' => $currentYaw,
				'pitch' => $currentPitch
			]);
			if (count($this->positionHistory[$trainId]) > $this->minTrainId) {
				array_shift($this->positionHistory[$trainId]);
			}

			if (!empty($this->followers[$level])) {
				$prev = $train;
				foreach ($this->followers[$level] as $id => $crew) {
					// 双重有效性检查
					if ($prev->closed || $crew->closed || !isset($this->positionHistory[$prev->getId()])) {
						unset($this->followers[$level][$id], $this->positionHistory[$crew->getId()]);
						continue;
					}

					// 取前车的历史位置（延后）
					$prevHistory = $this->positionHistory[$prev->getId()];
					$targetEntry = !empty($prevHistory) ? $prevHistory[0] : null; // 取最旧的位置
					if ($targetEntry) {
						if($crew->distance($prev) > $this->minDistance){
							$crew->setPositionAndRotation(
								$targetEntry['pos'],
								$targetEntry['yaw'],
								$targetEntry['pitch']
							);
						}
					}

					// 记录当前车厢位置历史
					$crewId = $crew->getId();
					if (!isset($this->positionHistory[$crewId])) {
						$this->positionHistory[$crewId] = [];
					}
					array_push($this->positionHistory[$crewId], [
						'pos' => $crew->getPosition(),
						'yaw' => $crew->getYaw(),
						'pitch' => $crew->getPitch()
					]);
					if (count($this->positionHistory[$crewId]) > $this->minTrainId) {
						array_shift($this->positionHistory[$crewId]);
					}

					// 更新前车引用
					$prev = $crew;
				}
			}
		}
	}

    private function addCrew(Player $p) {
        $level = $p->getLevel()->getName();
        $nbt = new CompoundTag("", [
            new ListTag("Pos", [
                new DoubleTag("", $p->x),
                new DoubleTag("", $p->y),
                new DoubleTag("", $p->z)
            ]),
            new ListTag("Rotation", [
                new FloatTag("", 0),
                new FloatTag("", 0)
            ]),
            new ShortTag("Health", 1),
            new StringTag("CustomName", "")
        ]);

        $chunk = $p->getLevel()->getChunk($p->x >> 4, $p->z >> 4, true);
        $crew = Entity::createEntity("Crew", $chunk, $nbt);
        
        if ($crew instanceof Crew) {
            $crew->spawnToAll();
            $crew->setLinked(1, $p);
            $p->setLinked(1, $crew);
            $this->followers[$level][$crew->getId()] = $crew;
        }
    }

    public function onQuit(PlayerQuitEvent $ev) {
        $p = $ev->getPlayer();
        $level = $p->getLevel()->getName();

        // 处理车头
        if (isset($this->trains[$level])) {
            $train = $this->trains[$level];
            if ($train->getLinkedEntity() === $p) {
                $train->close();
                unset($this->trains[$level], $this->lastPos[$train->getId()]);
            }
        }

        // 处理车厢
        if (isset($this->followers[$level])) {
            foreach ($this->followers[$level] as $id => $crew) {
                if ($crew->getLinkedEntity() === $p) {
                    $crew->close();
                    unset($this->followers[$level][$id], $this->lastPos[$crew->getId()]);
                }
            }
        }
    }
}
