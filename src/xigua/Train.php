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

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;
use pocketmine\math\Math;
use pocketmine\entity\Vehicle;

class Train extends Vehicle{
	const NETWORK_ID = 84;//84

	public $height = 0.9;
	public $width = 1.1;

	public $isMoving = false;
	
	public $moveSpeed = 0.5;

	public function initEntity(){
		$this->setMaxHealth(25565);
		$this->setHealth($this->getMaxHealth());
		parent::initEntity();
	}

	public function getName() : string{
		return "Train";
	}

	public function onUpdate($currentTick){
		if($this->closed !== false){
			return false;
		}

		$this->lastUpdate = $currentTick;

		$this->timings->startTiming();

		$hasUpdate = false;
		//parent::onUpdate($currentTick);
		$this->moveSpeed = 0.75;
		if($this->isAlive()){
			$p = $this->getLinkedEntity();
			if($p instanceof Player){
				//暂时没有更好的方法修复这个漏洞
				if($p->getYaw() == null){
					$this->close();
				}
				// 保留原始角度转换方式
				$yaw = $p->getYaw() / 180 * M_PI;
				$pitch = $p->getPitch() / 180 * M_PI;
				
				// 修改运动计算（三维化）
				$horizontalFactor = cos($pitch); // 俯仰角影响水平速度
				$this->motionX = -sin($yaw) * $horizontalFactor;
				$this->motionZ = cos($yaw) * $horizontalFactor;
				$this->motionY = -sin($pitch);
				
				$this->motionX *= $this->moveSpeed;
				$this->motionZ *= $this->moveSpeed;
				$this->motionY *= $this->moveSpeed;
			}

			$target = $this->getLevel()->getBlock($this->add($this->motionX, $this->motionY, $this->motionZ)->round());
			$target2 = $this->getLevel()->getBlock($this->add($this->motionX, $this->motionY, $this->motionZ)->floor());
			
			// 三维碰撞检测（原逻辑扩展）
			if($target->getId() != Item::AIR || $target2->getId() != Item::AIR){
				$hasUpdate = true;
				// 碰撞时反向运动（简单处理）
				$this->motionX *= -0.5;
				$this->motionY *= -0.5;
				$this->motionZ *= -0.5;
			}

			if($this->checkObstruction($this->x, $this->y, $this->z)){
				$hasUpdate = true;
			}

			$this->move($this->motionX, $this->motionY, $this->motionZ);
			$this->setRotation($p->getYaw() - 90, $p->getPitch());
			$this->updateMovement();

			$hasUpdate = true;
		}else{
			$this->close();
		}
		$this->timings->stopTiming();

		return $hasUpdate or !$this->onGround or abs($this->motionX) > 0.00001 or abs($this->motionY) > 0.00001 or abs($this->motionZ) > 0.00001;
	}

	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = Train::NETWORK_ID;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->speedX = 0;
		$pk->speedY = 0;
		$pk->speedZ = 0;
		$pk->yaw = 0;
		$pk->pitch = 0;
		$pk->metadata = $this->dataProperties;
		$player->dataPacket($pk);

		parent::spawnTo($player);
	}
	
	public function close(){
		parent::close();
	}
	
	public function setSpeed($speed){
		$this->moveSpeed = $speed;
	}
}
