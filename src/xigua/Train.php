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
                                           
 * Copyright (c) 2025 xigua7265(xigua)
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
use pocketmine\item\Item as ItemItem;
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

//优化代码源自sunch233（qq:2125696621）
    public function entityBaseTick($tickDiff = 1){
        $p = $this->getLinkedEntity();
        if($p instanceof Player){
            if(!$p->isAlive()){
                $this->close();
            }
            //矿车运行逻辑
            $playerDirection =  $p->getDirectionVector();
            $this->motionX = $playerDirection->x * $this->moveSpeed;
            $this->motionY = $playerDirection->y * $this->moveSpeed;
            $this->motionZ = $playerDirection->z * $this->moveSpeed;

            $this->setRotation($p->getYaw() - 90, $p->getPitch());
        }else{
            $this->close();
        }
        $this->checkObstruction($this->x, $this->y, $this->z);

        $this->move($this->motionX, $this->motionY, $this->motionZ);
    }
//

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
