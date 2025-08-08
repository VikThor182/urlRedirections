# Variables
DOCKER_COMPOSE = docker-compose
APP_NAME = urlredirection-app

# Couleurs pour les messages
GREEN = \033[0;32m
YELLOW = \033[1;33m
RED = \033[0;31m
NC = \033[0m # No Color

.PHONY: help up down build rebuild logs clean status

# Commande par défaut
help: ## Affiche cette aide
	@echo "$(GREEN)🔄 Application de Redirection d'URLs$(NC)"
	@echo ""
	@echo "$(YELLOW)Commandes disponibles :$(NC)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-15s$(NC) %s\n", $$1, $$2}'
	@echo ""

up: ## Démarre l'application (build + run)
	@echo "$(GREEN)🚀 Démarrage de l'application...$(NC)"
	@$(DOCKER_COMPOSE) up -d --build
	@echo "$(GREEN)✅ Application démarrée !$(NC)"
	@echo "$(YELLOW)📱 Ouvrez votre navigateur sur : http://localhost:8000$(NC)"

down: ## Arrête l'application
	@echo "$(YELLOW)🛑 Arrêt de l'application...$(NC)"
	@$(DOCKER_COMPOSE) down
	@echo "$(GREEN)✅ Application arrêtée !$(NC)"

build: ## Construit l'image Docker
	@echo "$(GREEN)🔨 Construction de l'image Docker...$(NC)"
	@$(DOCKER_COMPOSE) build

rebuild: ## Reconstruit l'image Docker (sans cache)
	@echo "$(GREEN)🔨 Reconstruction complète de l'image Docker...$(NC)"
	@$(DOCKER_COMPOSE) build --no-cache

logs: ## Affiche les logs de l'application
	@echo "$(GREEN)📋 Logs de l'application :$(NC)"
	@$(DOCKER_COMPOSE) logs -f

status: ## Affiche le statut de l'application
	@echo "$(GREEN)📊 Statut de l'application :$(NC)"
	@$(DOCKER_COMPOSE) ps

clean: ## Nettoie les conteneurs et images Docker
	@echo "$(YELLOW)🧹 Nettoyage...$(NC)"
	@$(DOCKER_COMPOSE) down -v --remove-orphans
	@docker system prune -f
	@echo "$(GREEN)✅ Nettoyage terminé !$(NC)"

restart: down up ## Redémarre l'application

install: ## Installation complète (première utilisation)
	@echo "$(GREEN)📦 Installation de l'application...$(NC)"
	@echo "$(YELLOW)Vérification de Docker...$(NC)"
	@docker --version > /dev/null 2>&1 || (echo "$(RED)❌ Docker n'est pas installé !$(NC)" && exit 1)
	@docker-compose --version > /dev/null 2>&1 || (echo "$(RED)❌ Docker Compose n'est pas installé !$(NC)" && exit 1)
	@echo "$(GREEN)✅ Docker détecté !$(NC)"
	@make up
	@echo ""
	@echo "$(GREEN)🎉 Installation terminée !$(NC)"
	@echo "$(YELLOW)📱 Ouvrez votre navigateur sur : http://localhost:8000$(NC)"

# Commandes pour le développement
dev-logs: ## Logs en temps réel pour le développement
	@$(DOCKER_COMPOSE) logs -f urlredirection

dev-shell: ## Accès shell au conteneur (pour debug)
	@docker exec -it $(APP_NAME) /bin/bash
