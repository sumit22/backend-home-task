<?php

namespace App\Entity;

use App\Repository\RepositoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\Trait\HasTimeStamps;
use App\Entity\Trait\HasId;

#[ORM\Entity(repositoryClass: RepositoryRepository::class)]
#[ORM\Table(name: 'repository')]
#[ORM\HasLifecycleCallbacks]
class Repository
{
    use HasTimeStamps;
    use HasId;

    #[ORM\Column(length: 512)]
    private ?string $name = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $defaultBranch = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $settings = null;

    

    /**
     * @var Collection<int, NotificationSetting>
     */
    #[ORM\OneToMany(targetEntity: NotificationSetting::class, mappedBy: 'repository')]
    private Collection $notificationSettings;

    /**
     * @var Collection<int, RepositoryScan>
     */
    #[ORM\OneToMany(targetEntity: RepositoryScan::class, mappedBy: 'repository')]
    private Collection $repositoryScans;

    public function __construct()
    {
        $this->initializeId();
        $this->notificationSettings = new ArrayCollection();
        $this->repositoryScans = new ArrayCollection();
    }

    

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getDefaultBranch(): ?string
    {
        return $this->defaultBranch;
    }

    public function setDefaultBranch(?string $defaultBranch): static
    {
        $this->defaultBranch = $defaultBranch;

        return $this;
    }

    public function getSettings(): ?array
    {
        return $this->settings;
    }

    public function setSettings(?array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    

    /**
     * @return Collection<int, NotificationSetting>
     */
    public function getNotificationSettings(): Collection
    {
        return $this->notificationSettings;
    }

    public function addNotificationSetting(NotificationSetting $notificationSetting): static
    {
        if (!$this->notificationSettings->contains($notificationSetting)) {
            $this->notificationSettings->add($notificationSetting);
            $notificationSetting->setRepository($this);
        }

        return $this;
    }

    public function removeNotificationSetting(NotificationSetting $notificationSetting): static
    {
        if ($this->notificationSettings->removeElement($notificationSetting)) {
            if ($notificationSetting->getRepository() === $this) {
                $notificationSetting->setRepository(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, RepositoryScan>
     */
    public function getRepositoryScans(): Collection
    {
        return $this->repositoryScans;
    }

    public function addRepositoryScan(RepositoryScan $repositoryScan): static
    {
        if (!$this->repositoryScans->contains($repositoryScan)) {
            $this->repositoryScans->add($repositoryScan);
            $repositoryScan->setRepository($this);
        }

        return $this;
    }

    public function removeRepositoryScan(RepositoryScan $repositoryScan): static
    {
        if ($this->repositoryScans->removeElement($repositoryScan)) {
            if ($repositoryScan->getRepository() === $this) {
                $repositoryScan->setRepository(null);
            }
        }

        return $this;
    }
}
