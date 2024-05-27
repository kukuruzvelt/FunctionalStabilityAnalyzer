# Используем официальный образ Node.js
FROM node:22 as build

# Устанавливаем директорию приложения внутри контейнера
WORKDIR /app

# Копируем package.json и package-lock.json для установки зависимостей
COPY package*.json ./

# Устанавливаем зависимости
RUN npm install

# Копируем остальные файлы приложения
COPY . .

# Собираем приложение
RUN npm run build

# Используем образ nginx для хостинга статических файлов
FROM nginx:alpine

# Копируем собранные файлы из предыдущего образа в директорию nginx
COPY --from=build /app/build /usr/share/nginx/html

# Экспонируем порт 80
EXPOSE 80

# Команда для запуска nginx в режиме daemon
CMD ["nginx", "-g", "daemon off;"]
