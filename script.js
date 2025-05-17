document.addEventListener("DOMContentLoaded", () => {
  // DOM Elements
  const sidebar = document.getElementById("sidebar")
  const openSidebarBtn = document.getElementById("openSidebar")
  const closeSidebarBtn = document.getElementById("closeSidebar")

  const uploadModal = document.getElementById("uploadModal")
  const newFolderModal = document.getElementById("newFolderModal")
  const newFileModal = document.getElementById("newFileModal")
  const renameModal = document.getElementById("renameModal")
  const deleteModal = document.getElementById("deleteModal")

  const uploadForm = document.getElementById("uploadForm")
  const newFolderForm = document.getElementById("newFolderForm")
  const newFileForm = document.getElementById("newFileForm")
  const renameForm = document.getElementById("renameForm")

  // Multiple buttons for the same action
  const openUploadModalBtns = [
    document.getElementById("openUploadModalBtn"),
    document.getElementById("openUploadModalTopBtn"),
    document.getElementById("openUploadModalAction"),
  ]

  const openNewFolderModalBtns = [
    document.getElementById("openNewFolderModalBtn"),
    document.getElementById("openNewFolderModalAction"),
  ]

  const openNewFileModalBtns = [
    document.getElementById("openNewFileModalBtn"),
    document.getElementById("openNewFileModalAction"),
  ]

  const submitUploadBtn = document.getElementById("submitUpload")
  const submitNewFolderBtn = document.getElementById("submitNewFolder")
  const submitNewFileBtn = document.getElementById("submitNewFile")
  const submitRenameBtn = document.getElementById("submitRename")
  const confirmDeleteBtn = document.getElementById("confirmDelete")
  const refreshBtn = document.getElementById("refreshBtn")

  const dropzone = document.getElementById("dropzone")
  const fileInput = document.getElementById("fileInput")
  const selectedFiles = document.getElementById("selectedFiles")
  const filePreviewList = document.getElementById("filePreviewList")

  const codeEditor = document.getElementById("codeEditor")
  const toggleThemeBtn = document.querySelector(".toggle-theme-btn")
  const fullscreenToggleBtn = document.querySelector(".fullscreen-toggle")
  const editorCard = document.getElementById("editorCard")

  const toastContainer = document.getElementById("toastContainer")
  const themeToggle = document.getElementById("themeToggle")

  // Chat AI elements
  const chatMessages = document.getElementById("chatMessages")
  const chatForm = document.getElementById("chatForm")
  const chatInput = document.getElementById("chatInput")
  const chatSend = document.getElementById("chatSend")
  const chatFile = document.getElementById("chatFile")
  const chatFilePreview = document.getElementById("chatFilePreview")
  const chatTyping = document.getElementById("chatTyping")
  const chatError = document.getElementById("chatError")
  const chatRetry = document.getElementById("chatRetry")
  const chatEmpty = document.getElementById("chatEmpty")
  const newChatBtn = document.getElementById("newChatBtn")
  const newChatSession = document.getElementById("newChatSession")

  // Chat session ID
  let chatSessionId = ""
  const generateSessionId = () => {
    return "session_" + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15)
  }
  if (typeof PHP_CHAT_SESSION_ID !== "undefined") {
    chatSessionId = PHP_CHAT_SESSION_ID
  } else {
    chatSessionId = generateSessionId()
  }

  let lastMessage = null
  let chatHistory = []

  // Initialize CodeMirror editor
  let editor
  if (codeEditor) {
    const mode = codeEditor.getAttribute("data-mode") || "text/plain"
    editor = CodeMirror.fromTextArea(codeEditor, {
      mode: mode,
      theme: document.documentElement.getAttribute("data-theme") === "dark" ? "dracula" : "eclipse",
      lineNumbers: true,
      lineWrapping: true,
      autoCloseBrackets: true,
      autoCloseTags: true,
      matchBrackets: true,
      indentUnit: 4,
      tabSize: 4,
      indentWithTabs: false,
      extraKeys: {
        "Ctrl-Space": "autocomplete",
        "Ctrl-/": "toggleComment",
        "Ctrl-F": "findPersistent",
        F11: (cm) => {
          toggleFullscreen()
        },
        Esc: (cm) => {
          if (editorCard && editorCard.classList.contains("fullscreen")) {
            toggleFullscreen()
          }
        },
      },
      foldGutter: true,
      gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
      styleActiveLine: true,
      autoRefresh: true,
      viewportMargin: Number.POSITIVE_INFINITY,
    })

    // Force refresh editor
    setTimeout(() => editor.refresh(), 100)
    setTimeout(() => editor.refresh(), 500)
    setTimeout(() => editor.refresh(), 1000)
    setTimeout(() => editor.refresh(), 2000)
  }

  // Sidebar toggle
  if (openSidebarBtn) {
    openSidebarBtn.addEventListener("click", () => {
      sidebar.classList.add("show")
    })
  }

  if (closeSidebarBtn) {
    closeSidebarBtn.addEventListener("click", () => {
      sidebar.classList.remove("show")
    })
  }

  // Modal functions
  function openModal(modal) {
    if (!modal) return
    modal.classList.add("show")
    document.body.style.overflow = "hidden"
  }

  function closeModal(modal) {
    if (!modal) return
    modal.classList.remove("show")
    document.body.style.overflow = ""
  }

  // Close modal when clicking outside
  document.querySelectorAll(".modal-backdrop").forEach((modal) => {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) {
        closeModal(modal)
      }
    })
  })

  // Close modal when clicking close button
  document.querySelectorAll('[data-dismiss="modal"]').forEach((btn) => {
    btn.addEventListener("click", () => {
      const modal = btn.closest(".modal-backdrop")
      closeModal(modal)
    })
  })

  // Open upload modal
  openUploadModalBtns.forEach((btn) => {
    if (btn) {
      btn.addEventListener("click", () => {
        openModal(uploadModal)
      })
    }
  })

  // Open new folder modal
  openNewFolderModalBtns.forEach((btn) => {
    if (btn) {
      btn.addEventListener("click", () => {
        openModal(newFolderModal)
      })
    }
  })

  // Open new file modal
  openNewFileModalBtns.forEach((btn) => {
    if (btn) {
      btn.addEventListener("click", () => {
        openModal(newFileModal)
      })
    }
  })

  // Submit upload form
  if (submitUploadBtn) {
    submitUploadBtn.addEventListener("click", () => {
      if (fileInput.files.length > 0) {
        uploadForm.submit()
      } else {
        showToast("warning", "Warning", "Please select at least one file to upload.")
      }
    })
  }

  // Submit new folder form
  if (submitNewFolderBtn) {
    submitNewFolderBtn.addEventListener("click", () => {
      if (newFolderForm.checkValidity()) {
        newFolderForm.submit()
      } else {
        showToast("warning", "Warning", "Please enter a folder name.")
      }
    })
  }

  // Submit new file form
  if (submitNewFileBtn) {
    submitNewFileBtn.addEventListener("click", () => {
      if (newFileForm.checkValidity()) {
        newFileForm.submit()
      } else {
        showToast("warning", "Warning", "Please enter a file name.")
      }
    })
  }

  // Submit rename form
  if (submitRenameBtn) {
    submitRenameBtn.addEventListener("click", () => {
      if (renameForm.checkValidity()) {
        renameForm.submit()
      } else {
        showToast("warning", "Warning", "Please enter a new name.")
      }
    })
  }

  // Refresh page
  if (refreshBtn) {
    refreshBtn.addEventListener("click", () => {
      window.location.reload()
    })
  }

  // Dropzone functionality
  if (dropzone) {
    dropzone.addEventListener("click", () => {
      fileInput.click()
    })

    dropzone.addEventListener("dragover", (e) => {
      e.preventDefault()
      dropzone.classList.add("dragover")
    })

    dropzone.addEventListener("dragleave", () => {
      dropzone.classList.remove("dragover")
    })

    dropzone.addEventListener("drop", (e) => {
      e.preventDefault()
      dropzone.classList.remove("dragover")

      if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files
        updateSelectedFiles()
      }
    })

    fileInput.addEventListener("change", updateSelectedFiles)

    function updateSelectedFiles() {
      if (fileInput.files.length) {
        filePreviewList.innerHTML = ""

        for (let i = 0; i < fileInput.files.length; i++) {
          const file = fileInput.files[i]
          const fileItem = document.createElement("div")
          fileItem.className = "file-preview-item"
          fileItem.innerHTML = `
                        <div class="file-preview-name">
                            <i class="fas fa-file"></i>
                            <span>${file.name}</span>
                        </div>
                        <div class="file-preview-size">${formatFileSize(file.size)}</div>
                    `
          filePreviewList.appendChild(fileItem)
        }

        selectedFiles.style.display = "block"
      } else {
        selectedFiles.style.display = "none"
      }
    }

    function formatFileSize(bytes) {
      if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + " GB"
      if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + " MB"
      if (bytes >= 1024) return (bytes / 1024).toFixed(2) + " KB"
      return bytes + " B"
    }
  }

  // Rename functionality
  document.querySelectorAll(".rename-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const name = btn.getAttribute("data-name")
      document.getElementById("renameFrom").value = name
      document.getElementById("newName").value = name
      openModal(renameModal)
    })
  })

  // Delete functionality
  document.querySelectorAll(".delete-btn").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault()
      const name = btn.getAttribute("data-name")
      const deleteUrl = btn.getAttribute("data-path")

      document.getElementById("deleteItemName").textContent = name
      document.getElementById("confirmDelete").setAttribute("href", deleteUrl)

      openModal(deleteModal)
    })
  })

  // Chmod functionality - SIMPLIFIED VERSION
  document.querySelectorAll(".chmod-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const name = btn.getAttribute("data-name")
      const path = btn.getAttribute("data-path")

      document.getElementById("chmodItemName").textContent = name
      document.getElementById("chmodPath").value = path

      // Get current permissions
      fetch(`?path=${encodeURIComponent(path)}&get_permissions=1`)
        .then((response) => response.json())
        .then((data) => {
          const perms = data.permissions
          
          // Set direct permission input value - convert to octal string without leading 0
          const octalValue = (perms & 0o777).toString(8).padStart(3, "0")
          document.getElementById("directPermValue").value = octalValue
        })
        .catch((error) => {
          console.error("Error fetching permissions:", error)
          // Set default permissions (755)
          document.getElementById("directPermValue").value = "755"
        })

      openModal(document.getElementById("chmodModal"))
    })
  })

  // Submit chmod form - SIMPLIFIED VERSION
  const submitChmodBtn = document.getElementById("submitChmod")
  if (submitChmodBtn) {
    submitChmodBtn.addEventListener("click", () => {
      // Ensure only valid octal digits
      const permInput = document.getElementById("directPermValue")
      permInput.value = permInput.value.replace(/[^0-7]/g, "").substring(0, 4)
      
      // Submit form
      document.getElementById("chmodForm").submit()
    })
  }

  // GSocket output modal
  const gsocketOutputModal = document.getElementById("gsocketOutputModal")
  if (gsocketOutputModal) {
    document.querySelectorAll('#gsocketOutputModal [data-dismiss="modal"]').forEach((btn) => {
      btn.addEventListener("click", () => {
        closeModal(gsocketOutputModal)
      })
    })
  }

  // Theme toggle functionality
  if (toggleThemeBtn && editor) {
    toggleThemeBtn.addEventListener("click", () => {
      const currentTheme = editor.getOption("theme")
      const newTheme = currentTheme === "eclipse" ? "dracula" : "eclipse"
      editor.setOption("theme", newTheme)

      const icon = toggleThemeBtn.querySelector("i")
      if (newTheme === "dracula") {
        icon.classList.remove("fa-moon")
        icon.classList.add("fa-sun")
      } else {
        icon.classList.remove("fa-sun")
        icon.classList.add("fa-moon")
      }
    })
  }

  // Global theme toggle
  if (themeToggle) {
    themeToggle.addEventListener("click", () => {
      const currentTheme = document.documentElement.getAttribute("data-theme")
      const newTheme = currentTheme === "dark" ? "light" : "dark"

      document.documentElement.setAttribute("data-theme", newTheme)

      // Update button icon and text
      const icon = themeToggle.querySelector("i")
      const text = themeToggle.querySelector("span")

      if (newTheme === "dark") {
        icon.classList.remove("fa-moon")
        icon.classList.add("fa-sun")
        text.textContent = "Light Mode"
      } else {
        icon.classList.remove("fa-sun")
        icon.classList.add("fa-moon")
        text.textContent = "Dark Mode"
      }

      // Save theme preference in cookie
      document.cookie = `theme=${newTheme}; path=/; max-age=31536000`

      // Update editor theme if editor exists
      if (editor) {
        editor.setOption("theme", newTheme === "dark" ? "dracula" : "eclipse")
      }
    })
  }

  // Toast notification function
  window.showToast = (type, title, message, duration = 5000) => {
    const toast = document.createElement("div")
    toast.className = `toast toast-${type}`

    let icon = "info-circle"
    if (type === "success") icon = "check-circle"
    if (type === "danger") icon = "exclamation-circle"
    if (type === "warning") icon = "exclamation-triangle"

    toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${icon}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close">
                <i class="fas fa-times"></i>
            </button>
            <div class="toast-progress">
                <div class="toast-progress-bar" style="animation-duration: ${duration}ms"></div>
            </div>
        `

    toastContainer.appendChild(toast)

    toast.querySelector(".toast-close").addEventListener("click", () => {
      toast.classList.add("hide")
      setTimeout(() => {
        toast.remove()
      }, 300)
    })

    setTimeout(() => {
      if (toast.parentNode) {
        toast.classList.add("hide")
        setTimeout(() => {
          if (toast.parentNode) {
            toast.remove()
          }
        }, 300)
      }
    }, duration)
  }

  // Check if mobile
  function isMobile() {
    return window.innerWidth < 768
  }

  // Close sidebar on mobile when clicking a link
  if (isMobile()) {
    document.querySelectorAll(".sidebar a").forEach((link) => {
      link.addEventListener("click", () => {
        sidebar.classList.remove("show")
      })
    })
  }

  // Resize handler
  window.addEventListener("resize", () => {
    if (!isMobile()) {
      sidebar.classList.remove("show")
    }

    // Resize editor
    if (editor) {
      const editorContainer = document.querySelector(".editor-container")
      if (editorContainer) {
        const availableHeight = window.innerHeight - 150
        editorContainer.style.height = availableHeight + "px"

        setTimeout(() => {
          editor.refresh()
        }, 100)
      }
    }
  })

  // Keyboard shortcuts
  document.addEventListener("keydown", (e) => {
    // Close modals with Escape
    if (e.key === "Escape") {
      document.querySelectorAll(".modal-backdrop.show").forEach((modal) => {
        closeModal(modal)
      })
    }

    // Save file with Ctrl+S
    if (e.key === "s" && (e.ctrlKey || e.metaKey) && editor) {
      if (document.querySelector('form[method="post"] button[type="submit"]')) {
        e.preventDefault()
        document.querySelector('form[method="post"]').submit()
      }
    }
  })

  // Chat AI functionality
  if (chatForm) {
    let uploadedFile = null

    // Initialize code input handling
    handleUserCodeInput()

    // Handle file uploads for chat
    chatFile.addEventListener("change", async (e) => {
      if (chatFile.files.length > 0) {
        const file = chatFile.files[0]

        // Preview file
        chatFilePreview.innerHTML = `
            <div class="chat-file">
                <div class="chat-file-icon">
                    <i class="fas fa-${file.type.startsWith("image/") ? "image" : "file"}"></i>
                </div>
                <div class="chat-file-info">
                    <div class="chat-file-name">${file.name}</div>
                    <div class="chat-file-size">${formatFileSize(file.size)}</div>
                </div>
                <div class="chat-file-remove" id="removeFile">
                    <i class="fas fa-times"></i>
                </div>
            </div>
        `

        // If it's an image, show preview
        if (file.type.startsWith("image/")) {
          const reader = new FileReader()
          reader.onload = (e) => {
            chatFilePreview.innerHTML += `
                    <img src="${e.target.result}" class="chat-file-preview" alt="${file.name}">
                `
          }
          reader.readAsDataURL(file)
        }

        // Upload file to server
        const formData = new FormData()
        formData.append("file", file)

        try {
          // Upload file to server
          const upload = await axios.post("https://api.eclair.web.id/api/upload", formData, {
            headers: {
              "Content-Type": "multipart/form-data",
            },
          })

          if (upload.data && upload.data.url) {
            uploadedFile = {
              name: file.name,
              size: file.size,
              type: file.type,
              url: upload.data.url,
            }
            console.log("File uploaded successfully:", uploadedFile)
          } else {
            throw new Error("Invalid upload response")
          }
        } catch (error) {
          console.error("Error uploading file:", error)
          showToast("danger", "Error", "Failed to upload file. Please try again.")
        }

        // Add event listener to remove file button
        document.getElementById("removeFile").addEventListener("click", () => {
          chatFilePreview.innerHTML = ""
          chatFile.value = ""
          uploadedFile = null
        })
      }
    })

    // Handle chat form submission
    chatForm.addEventListener("submit", async (e) => {
      e.preventDefault()

      const message = chatInput.value.trim()
      if (!message && !uploadedFile) return

      // Add user message to chat
      addMessage(message, "user")

      // Clear input
      chatInput.value = ""

      // Show typing indicator
      chatTyping.style.display = "flex"
      chatEmpty.style.display = "none"

      try {
        // Send message to AI
        const fileUrl = uploadedFile ? uploadedFile.url : null

        // Make API request
        const response = await sendMessageToAI(message, fileUrl)

        // Add AI response to chat
        addMessage(response.text, "ai")

        // Clear file preview and uploaded file
        chatFilePreview.innerHTML = ""
        chatFile.value = ""
        uploadedFile = null
      } catch (error) {
        console.error("Error sending message:", error)
        chatError.style.display = "flex"
      }

      // Hide typing indicator
      chatTyping.style.display = "none"
    })

    // Retry button
    if (chatRetry) {
      chatRetry.addEventListener("click", async () => {
        chatError.style.display = "none"

        if (lastMessage) {
          // Show typing indicator
          chatTyping.style.display = "flex"

          try {
            // Retry sending the last message
            const response = await sendMessageToAI(lastMessage.text, lastMessage.fileUrl)

            // Add AI response to chat
            addMessage(response.text, "ai")
          } catch (error) {
            console.error("Error retrying message:", error)
            chatError.style.display = "flex"
          }

          // Hide typing indicator
          chatTyping.style.display = "none"
        }
      })
    }

    // New chat button
    if (newChatBtn) {
      newChatBtn.addEventListener("click", () => {
        // Generate new session ID
        chatSessionId = generateSessionId()

        // Clear chat history
        chatHistory = []

        // Clear chat messages
        chatMessages.innerHTML = ""
        chatEmpty.style.display = "flex"

        // Show toast
        showToast("info", "New Chat", "Started a new conversation with EclipseAI.")
      })
    }

    // New chat session button
    if (newChatSession) {
      newChatSession.addEventListener("click", () => {
        // Generate new session ID
        chatSessionId = generateSessionId()

        // Clear chat history
        chatHistory = []

        // Clear chat messages
        chatMessages.innerHTML = ""
        chatEmpty.style.display = "flex"

        // Show toast
        showToast("info", "New Chat", "Started a new conversation with EclipseAI.")
      })
    }

    // Function to send message to AI
    async function sendMessageToAI(text, fileUrl = null) {
      try {
        // Store last message for retry
        lastMessage = { text, fileUrl }

        // Add to chat history
        chatHistory.push({ role: "user", content: text })

        // Check if this is a file read request
        if (text.toLowerCase().includes("read file") || text.toLowerCase().includes("show file")) {
          const fileMatch =
            text.match(/read file\s+["']?([^"']+)["']?/i) || text.match(/show file\s+["']?([^"']+)["']?/i)
          if (fileMatch && fileMatch[1]) {
            const fileName = fileMatch[1].trim()
            try {
              const response = await fetch(
                `?path=${encodeURIComponent(window.location.pathname)}&read_file=${encodeURIComponent(fileName)}`,
              )
              const data = await response.json()

              if (data.success) {
                const fileContent = data.content
                const fileType = fileName.split(".").pop().toLowerCase()

                const aiResponse = `Here's the content of "${fileName}":\n\n\`\`\`${fileType}\n${fileContent}\n\`\`\``

                // Add AI response to chat history
                chatHistory.push({ role: "assistant", content: aiResponse })

                return { text: aiResponse }
              } else {
                const errorMessage = `I couldn't read the file "${fileName}". ${data.error}`
                chatHistory.push({ role: "assistant", content: errorMessage })
                return { text: errorMessage }
              }
            } catch (error) {
              const errorMessage = `I couldn't read the file "${fileName}". The file might not exist or I don't have permission to read it.`
              chatHistory.push({ role: "assistant", content: errorMessage })
              return { text: errorMessage }
            }
          }
        }

        // Check if this is a code request that should be suppressed
        if (text.toLowerCase().includes("write code") || text.toLowerCase().includes("create code")) {
          const aiResponse =
            "I understand you want me to write code, but I've been configured not to output code directly. Would you like me to help you with something else?"
          chatHistory.push({ role: "assistant", content: aiResponse })
          return { text: aiResponse }
        }

        // Make API request
        const response = await axios.get("https://api.eclair.web.id/api/ai-session", {
          params: {
            id: chatSessionId,
            text: text,
            prompt: "Kamu adalah EclipseAi, Assisten AI Yang dapat membantu memecahkan masalah apapun!",
            file: fileUrl || "null",
            apikey: "kensdev",
          },
        })

        // Check if response data exists and has the expected structure
        if (response.data && response.data.data && response.data.data.message) {
          // Extract the message from the nested structure
          const aiMessage = response.data.data.message

          // Add AI response to chat history
          chatHistory.push({ role: "assistant", content: aiMessage })

          // Return in the format expected by the addMessage function
          return { text: aiMessage }
        } else {
          // Handle empty or invalid response
          throw new Error("Invalid response from AI service")
        }
      } catch (error) {
        console.error("Error in AI request:", error)
        throw error
      }
    }

    // Function to add message to chat
    function addMessage(text, sender) {
      const messageElement = document.createElement("div")
      messageElement.className = `chat-message chat-message-${sender}`

      const now = new Date()
      const timeString = now.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })

      // Process code blocks for AI messages
      let processedText = text
      if (sender === "ai") {
        // Replace code blocks with formatted HTML
        processedText = processCodeBlocks(text)
      }

      messageElement.innerHTML = `
                <div class="chat-avatar chat-avatar-${sender}">
                    ${
                      sender === "user"
                        ? document.querySelector(".user-avatar").textContent
                        : '<i class="fas fa-robot"></i>'
                    }
                </div>
                <div class="chat-bubble chat-bubble-${sender}">
                    <div class="chat-content chat-markdown">${processedText}</div>
                    <div class="chat-time ${sender === "ai" ? "chat-time-ai" : ""}">${timeString}</div>
                </div>
            `

      chatMessages.appendChild(messageElement)

      // Scroll to bottom
      chatMessages.scrollTop = chatMessages.scrollHeight
    }

    // Function to process code blocks in messages
    function processCodeBlocks(text) {
      // Replace code blocks with formatted HTML
      return text.replace(/```(\w+)?\n([\s\S]*?)```/g, (match, language, code) => {
        const lang = language || "plaintext"
        return `<div class="code-block">
                            <div class="code-header">
                                <span class="code-language">${lang}</span>
                                <button class="code-copy" onclick="copyCode(this)">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <pre><code class="language-${lang}">${escapeHtml(code.trim())}</code></pre>
                        </div>`
      })
    }

    // Function to escape HTML special characters
    function escapeHtml(text) {
      const div = document.createElement("div")
      div.textContent = text
      return div.innerHTML
    }

    // Function to copy code to clipboard
    window.copyCode = (button) => {
      const codeBlock = button.closest(".code-block")
      const code = codeBlock.querySelector("code").textContent

      navigator.clipboard
        .writeText(code)
        .then(() => {
          // Change button icon temporarily
          const icon = button.querySelector("i")
          icon.classList.remove("fa-copy")
          icon.classList.add("fa-check")

          setTimeout(() => {
            icon.classList.remove("fa-check")
            icon.classList.add("fa-copy")
          }, 2000)
        })
        .catch((err) => {
          console.error("Failed to copy code: ", err)
        })
    }

    // Add this function to handle user code input
    function handleUserCodeInput() {
      // Listen for triple backtick in user input
      chatInput.addEventListener("input", function () {
        const text = this.value
        if (text.includes("```")) {
          // If user is typing code, apply special styling
          this.style.fontFamily = "'Fira Code', monospace"
          this.style.backgroundColor = "#f8fafc"
        } else {
          // Reset styling for normal text
          this.style.fontFamily = ""
          this.style.backgroundColor = ""
        }
      })
    }
  }

  // Function to toggle fullscreen mode for the editor
  window.toggleFullscreen = () => {
    if (editorCard) {
      editorCard.classList.toggle("fullscreen")
      if (editorCard.classList.contains("fullscreen")) {
        document.body.style.overflow = "hidden"
        if (fullscreenToggleBtn) {
          const icon = fullscreenToggleBtn.querySelector("i")
          const text = fullscreenToggleBtn.querySelector("span")
          icon.classList.remove("fa-expand")
          icon.classList.add("fa-compress")
          text.textContent = "Exit Fullscreen"
        }
      } else {
        document.body.style.overflow = ""
        if (fullscreenToggleBtn) {
          const icon = fullscreenToggleBtn.querySelector("i")
          const text = fullscreenToggleBtn.querySelector("span")
          icon.classList.remove("fa-compress")
          icon.classList.add("fa-expand")
          text.textContent = "Fullscreen"
        }
      }

      // Force refresh the editor after toggling fullscreen
      if (editor) {
        setTimeout(() => {
          editor.refresh()
        }, 100)
      }
    }
  }

  // Add event listener for fullscreen toggle button
  if (fullscreenToggleBtn) {
    fullscreenToggleBtn.addEventListener("click", toggleFullscreen)
  }

  // Force refresh editor function
  function forceRefreshEditor() {
    if (editor) {
      editor.refresh()

      // Ensure editor is visible
      const editorElement = document.querySelector(".CodeMirror")
      if (editorElement) {
        editorElement.style.height = "auto"
        editorElement.style.minHeight = "500px"
        editorElement.style.display = "block"
        editorElement.style.visibility = "visible"
        editorElement.style.opacity = "1"
      }
    }
  }

  // Call refresh function when page loads
  forceRefreshEditor()

  // Refresh periodically during the first 10 seconds
  let refreshCount = 0
  const refreshInterval = setInterval(() => {
    forceRefreshEditor()
    refreshCount++
    if (refreshCount >= 10) {
      clearInterval(refreshInterval)
    }
  }, 1000)

  // Refresh when window is resized
  window.addEventListener("resize", forceRefreshEditor)

  // Refresh when tab becomes active
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
      forceRefreshEditor()
    }
  })

  function formatFileSize(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + " GB"
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + " MB"
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + " KB"
    return bytes + " B"
  }
})
