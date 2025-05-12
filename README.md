# Eclipse File Manager

![Eclipse File Manager](https://i.imgur.com/2KonUxK.png)

## Description

Eclipse File Manager is a powerful and modern web-based file management application. It allows users to manage files and folders through an intuitive and responsive interface. Equipped with an integrated code editor, AI chat features, and various advanced file management functions.

## Key Features

- **File & Folder Management**
  - File uploads (single and multi-file)
  - Create new files and folders
  - Rename, copy, move, and delete files/folders
  - Drag and drop to move files/folders

- **Integrated Code Editor**
  - Syntax highlighting for various programming languages
  - Dark and light themes
  - Auto-indentation and bracket matching
  - Line numbering and code folding

- **AI Chat Assistant**
  - Coding assistance with AI
  - Answers to programming-related questions
  - Coding suggestions and tips

- **Security Features**
  - User authentication
  - File access rights management
  - Sensitive data encryption

- **User Interface**
  - Responsive design (desktop and mobile)
  - Dark and light themes
  - Collapsible sidebar
  - Breadcrumb navigation

- **Additional Features**
  - File and folder search
  - File preview for various formats
  - Keyboard shortcuts
  - Activity history

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern browser (Chrome, Firefox, Safari, Edge)

## Installation

1. **Clone the repository**
   \`\`\`bash
   git clone https://github.com/username/eclipse-file-manager.git
   cd eclipse-file-manager
   \`\`\`

2. **Configure the database**
   - Create a new MySQL database
   - Import the `database.sql` file into your database
   - Copy `config.sample.php` to `config.php`
   - Edit `config.php` and adjust your database settings

3. **Configure the web server**
   - Point the document root to the `public` folder
   - Ensure mod_rewrite is enabled (for Apache)
   - Make sure the `.htaccess` file works correctly

4. **Install dependencies**
   \`\`\`bash
   composer install
   npm install
   npm run build
   \`\`\`

5. **Set permissions**
   \`\`\`bash
   chmod -R 755 public/uploads
   chmod -R 755 storage
   \`\`\`

6. **Access the application**
   - Open your browser and navigate to the application URL
   - Login with default credentials:
     - Username: admin
     - Password: admin123
   - Change the default password immediately after first login

## Usage

### File Navigation
- Use the sidebar to explore the folder structure
- Double-click on folders to open them
- Use the breadcrumb at the top for quick navigation

### Managing Files
- Right-click on files/folders for the context menu
- Use action buttons in the toolbar for common operations
- Drag and drop files to move them between folders

### Using the Editor
- Click on a file to open it in the editor
- Use the editor toolbar for operations like save, undo, redo
- Use keyboard shortcuts for faster navigation and editing

### AI Chat Assistant
- Click the chat icon in the bottom right corner to open the AI assistant
- Type questions or ask for coding-related help
- Use special commands like `/help` for additional assistance

## Advanced Configuration

Additional settings can be configured in the `config.php` file:

- `UPLOAD_MAX_SIZE`: Maximum upload file size (default: 50MB)
- `ALLOWED_EXTENSIONS`: File extensions allowed for upload
- `ENABLE_AI_FEATURES`: Enable/disable AI features (default: true)
- `DEFAULT_THEME`: Default theme (light/dark)
- `SESSION_TIMEOUT`: Session timeout in minutes

## Keyboard Shortcuts

- `Ctrl+S`: Save file
- `Ctrl+F`: Find in file
- `Ctrl+N`: New file
- `Ctrl+Shift+N`: New folder
- `Ctrl+O`: Open file
- `Ctrl+Shift+F`: Find in all files
- `F11`: Toggle fullscreen editor
- `Esc`: Exit fullscreen mode

## Troubleshooting

- **Files cannot be uploaded**
  - Check permissions on the `public/uploads` folder
  - Make sure the file size doesn't exceed the configured limit
  - Check if the file extension is allowed in the configuration

- **Editor not displaying syntax highlighting**
  - Make sure the file extension is recognized
  - Check the browser console for JavaScript errors

- **Authentication issues**
  - Clear browser cookies and try logging in again
  - Check database settings in `config.php`

## License

Eclipse File Manager is licensed under the [MIT License](LICENSE).

## Credits

- CodeMirror for the code editor
- Font Awesome for icons
- Bootstrap for CSS framework
- jQuery for DOM manipulation

## Contact & Support

For questions, suggestions, or bug reports, please contact:
- Email: support@eclipsefilemanager.com

---

&copy; 2025 Eclipse File Manager. All Rights Reserved.
